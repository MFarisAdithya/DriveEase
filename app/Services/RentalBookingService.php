<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalBookingService
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Create a new online or admin walk-in booking using identical business rules (C-05, A-15).
     *
     * @throws ValidationException
     */
    public function createBooking(
        User $customer,
        int $carId,
        CarbonInterface $pickupAt,
        int $totalDays,
        bool $termsAccepted,
        string $channel = Rental::CHANNEL_ONLINE,
        ?User $createdBy = null,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): Rental {
        if (! $customer->isCustomer()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pemesanan hanya dapat dibuat untuk akun pelanggan.',
            ]);
        }

        // Hanya pelanggan dengan KTP dan SIM terverifikasi yang berhak memesan kendaraan (C-03, A-15).
        if (! $customer->canMakeReservation()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Identitas pelanggan belum terverifikasi. Selesaikan verifikasi KTP dan SIM sebelum memesan.',
            ]);
        }

        if (! $termsAccepted) {
            throw ValidationException::withMessages([
                'accept_terms' => 'Anda wajib menyetujui ketentuan rental sebelum membuat pesanan.',
            ]);
        }

        if ($totalDays < 1 || $totalDays > RentalScheduleService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'total_days' => 'Jumlah hari sewa harus antara 1 sampai '.RentalScheduleService::MAX_DAYS.' hari.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $pickupWib = CarbonImmutable::instance($pickupAt)->timezone(RentalScheduleService::TIMEZONE);

        if ($pickupWib->lessThanOrEqualTo($nowWib)) {
            throw ValidationException::withMessages([
                'pickup_at' => 'Waktu pengambilan kendaraan harus berada di masa mendatang (WIB).',
            ]);
        }

        $normalizedChannel = in_array($channel, [Rental::CHANNEL_ONLINE, Rental::CHANNEL_WALK_IN], true)
            ? $channel
            : Rental::CHANNEL_ONLINE;
        $normalizedKey = is_string($idempotencyKey) && trim($idempotencyKey) !== ''
            ? substr(trim($idempotencyKey), 0, 64)
            : null;

        // DB::transaction() membungkus seluruh pengecekan dan penyimpanan dalam satu transaksi atomik.
        // lockForUpdate() mengunci baris mobil terpilih sehingga permintaan bersamaan dari web
        // maupun admin langsung menunggu giliran dan tidak pernah menghasilkan jadwal bentrok.
        return DB::transaction(function () use (
            $customer,
            $carId,
            $pickupWib,
            $totalDays,
            $normalizedChannel,
            $createdBy,
            $notes,
            $normalizedKey,
            $nowWib
        ): Rental {
            if ($normalizedKey !== null) {
                $existingRental = Rental::query()
                    ->where('idempotency_key', $normalizedKey)
                    ->where('user_id', $customer->id)
                    ->first();

                if ($existingRental !== null) {
                    return $existingRental;
                }
            }

            $car = Car::query()->lockForUpdate()->findOrFail($carId);

            if ($car->isArchived()) {
                throw ValidationException::withMessages([
                    'car_id' => 'Kendaraan ini telah diarsipkan dan tidak menerima pesanan baru.',
                ]);
            }

            if ($car->isInMaintenance()) {
                throw ValidationException::withMessages([
                    'car_id' => 'Kendaraan sedang dalam perawatan dan belum dapat dipesan.',
                ]);
            }

            if (! $car->isReadyForRental()) {
                throw ValidationException::withMessages([
                    'car_id' => 'Kendaraan saat ini belum siap untuk menerima pemesanan.',
                ]);
            }

            // Lepaskan pesanan unpaid yang sudah melewati tenggat pada mobil ini terlebih dahulu
            // sebelum memeriksa bentrokan jadwal.
            $this->scheduleService->releaseExpiredHolds($car->id, $nowWib);

            $pricing = $this->scheduleService->calculatePricing($car, $pickupWib, $totalDays);

            if ($this->scheduleService->hasScheduleConflict($car->id, $pricing['pickup_at'], $pricing['return_at'])) {
                throw ValidationException::withMessages([
                    'pickup_at' => 'Kendaraan tidak tersedia pada periode tersebut karena bentrok jadwal atau jeda persiapan 1 jam antarpenyewaan.',
                ]);
            }

            $paymentDeadline = $this->scheduleService->calculatePaymentDeadline($nowWib, $pricing['pickup_at']);
            $settings = RentalSetting::current();

            // Simpan tarif saat pemesanan serta salinan rekening tujuan aktif agar perubahan
            // harga atau rekening di masa depan tidak mengubah rincian tagihan pesanan ini (SYS-03, A-13).
            $rental = new Rental([
                'rental_code' => $this->generateUniqueRentalCode($nowWib),
                'user_id' => $customer->id,
                'car_id' => $car->id,
                'start_date' => $pricing['start_date'],
                'end_date' => $pricing['end_date'],
                'pickup_at' => $pricing['pickup_at'],
                'return_at' => $pricing['return_at'],
                'payment_deadline_at' => $paymentDeadline,
                'total_days' => $pricing['total_days'],
                'daily_price' => $pricing['daily_price'],
                'total_amount' => $pricing['total_amount'],
                'booking_channel' => $normalizedChannel,
                'created_by' => $createdBy?->id ?? $customer->id,
                'terms_accepted_at' => $nowWib,
                'payment_bank_name' => $settings?->bank_name,
                'payment_account_number' => $settings?->account_number,
                'payment_account_holder' => $settings?->account_holder,
                'status_notice' => 'Pesanan berhasil dibuat. Unggah bukti transfer maksimal pada '.$paymentDeadline->format('d M Y H:i').' WIB.',
                'notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
                'idempotency_key' => $normalizedKey,
            ]);

            $rental->rental_status = Rental::STATUS_PENDING;
            $rental->payment_status = Rental::PAYMENT_UNPAID;
            $rental->saveOrFail();

            return $rental;
        });
    }

    /**
     * Cancel an unpaid pending booking and release the schedule hold without deleting history (SYS-05).
     *
     * @throws ValidationException
     */
    public function cancelUnpaidBooking(Rental $rental, User $actor, ?string $reason = null): Rental
    {
        return DB::transaction(function () use ($rental, $actor, $reason): Rental {
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! $lockedRental->canBeCancelledUnpaid()) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Hanya pesanan berstatus menunggu dan belum dibayar yang dapat dibatalkan langsung pada tahap ini.',
                ]);
            }

            $cancelReason = is_string($reason) && trim($reason) !== ''
                ? trim($reason)
                : ($actor->isAdmin() ? 'Dibatalkan oleh admin sebelum pembayaran.' : 'Dibatalkan oleh pelanggan sebelum pembayaran.');

            $lockedRental->transitionRentalStatus(
                Rental::STATUS_CANCELLED,
                'Pesanan dibatalkan sebelum pembayaran. Jadwal kendaraan kini tersedia kembali.'
            );
            $lockedRental->cancelled_at = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
            $lockedRental->cancellation_reason = $cancelReason;
            $lockedRental->saveOrFail();

            return $lockedRental;
        });
    }

    /**
     * Generate a unique transaction code within the 20-character column limit (SYS-14).
     */
    private function generateUniqueRentalCode(CarbonImmutable $nowWib): string
    {
        // Loop do-while membuat kode DRV-YYMMDD-XXXXXX (17 karakter) dan memeriksa
        // tabel rentals hingga dipastikan belum pernah dipakai pesanan lain.
        do {
            $candidate = 'DRV-'.$nowWib->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Rental::query()->where('rental_code', $candidate)->exists());

        return $candidate;
    }
}
