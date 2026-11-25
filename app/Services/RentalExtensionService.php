<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalCashFlow;
use App\Models\RentalExtension;
use App\Models\RentalSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RentalExtensionService
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Ajukan perpanjangan masa sewa oleh pelanggan pada pesanan terkonfirmasi/sedang berjalan (C-09, SYS-09).
     *
     * @throws ValidationException
     */
    public function requestExtension(
        Rental $rental,
        User $customer,
        int $additionalDays,
        ?string $notes = null
    ): RentalExtension {
        if ($rental->user_id !== $customer->id) {
            throw ValidationException::withMessages([
                'additional_days' => 'Anda tidak berhak mengajukan perpanjangan untuk pesanan milik orang lain.',
            ]);
        }

        if ($additionalDays < 1 || $additionalDays > 30) {
            throw ValidationException::withMessages([
                'additional_days' => 'Tambahan hari perpanjangan harus antara 1 sampai 30 hari.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $trimmedNotes = trim((string) $notes);

        return DB::transaction(function () use ($rental, $customer, $additionalDays, $trimmedNotes, $nowWib): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $this->releaseExpiredExtensions($car->id, $nowWib);

            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            // Perpanjangan hanya diizinkan setelah pesanan awal lunas (confirmed atau picked_up).
            if (! in_array($lockedRental->rental_status, [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP], true)
                || $lockedRental->payment_status !== Rental::PAYMENT_PAID) {
                throw ValidationException::withMessages([
                    'additional_days' => 'Perpanjangan sewa hanya tersedia untuk pesanan terkonfirmasi atau sedang berjalan yang sudah lunas.',
                ]);
            }

            if ($this->hasOpenExtension($lockedRental)) {
                throw ValidationException::withMessages([
                    'additional_days' => 'Masih terdapat pengajuan perpanjangan yang sedang diproses pada pesanan ini.',
                ]);
            }

            $previousReturnAt = CarbonImmutable::instance($lockedRental->return_at)
                ->timezone(RentalScheduleService::TIMEZONE);
            $proposedReturnAt = $previousReturnAt->addHours($additionalDays * RentalScheduleService::HOURS_PER_DAY);
            $dailyPrice = (float) $lockedRental->daily_price;
            $additionalAmount = round($additionalDays * $dailyPrice, 2);

            $this->scheduleService->releaseExpiredHolds($car->id, $nowWib);

            if (! $this->scheduleService->isCarAvailableForPeriod($car, $lockedRental->pickup_at, $proposedReturnAt, $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'additional_days' => 'Kendaraan tidak tersedia untuk diperpanjang karena bentrok dengan jadwal pemesanan berikutnya atau masa jeda 1 jam.',
                ]);
            }

            $extension = RentalExtension::create([
                'rental_id' => $lockedRental->id,
                'requested_by' => $customer->id,
                'additional_days' => $additionalDays,
                'previous_return_at' => $previousReturnAt,
                'proposed_return_at' => $proposedReturnAt,
                'daily_price' => $dailyPrice,
                'additional_amount' => $additionalAmount,
                'paid_amount' => 0,
                'refund_amount' => 0,
                'refund_status' => RentalExtension::REFUND_NONE,
                'status' => RentalExtension::STATUS_PENDING_APPROVAL,
                'notes' => $trimmedNotes !== '' ? $trimmedNotes : null,
            ]);

            $lockedRental->forceFill([
                'status_notice' => 'Pengajuan perpanjangan sewa Anda sedang menunggu pemeriksaan ketersediaan dan persetujuan admin.',
            ])->save();

            $lockedRental->recordHistory(
                $customer,
                'extension_requested',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedNotes !== '' ? $trimmedNotes : null,
                [
                    'extension_id' => $extension->id,
                    'additional_days' => $additionalDays,
                    'previous_return_at' => $previousReturnAt->toIso8601String(),
                    'proposed_return_at' => $proposedReturnAt->toIso8601String(),
                    'additional_amount' => $additionalAmount,
                ]
            );

            return $extension;
        });
    }

    /**
     * Setujui pengajuan perpanjangan oleh admin setelah pemeriksaan ketersediaan tahap pertama (A-08, SYS-09, A-13).
     *
     * @throws ValidationException
     */
    public function approveExtension(RentalExtension $extension, User $admin, ?string $notes = null): RentalExtension
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'extension_id' => 'Hanya admin yang berhak menyetujui pengajuan perpanjangan.',
            ]);
        }

        $settings = RentalSetting::current();
        if (! ($settings?->hasValidPaymentAccount() ?? false)) {
            throw ValidationException::withMessages([
                'extension_id' => 'Rekening pembayaran rental belum dikonfigurasi dengan lengkap.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $trimmedNotes = trim((string) $notes);

        return DB::transaction(function () use ($extension, $admin, $settings, $trimmedNotes, $nowWib): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
            $car = Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

            if ($lockedExtension->status !== RentalExtension::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Pengajuan perpanjangan ini tidak lagi berstatus menunggu persetujuan.',
                ]);
            }

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP], true)) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Perpanjangan hanya dapat disetujui selama pesanan berstatus terkonfirmasi atau sedang berjalan.',
                ]);
            }

            $proposedReturnAt = CarbonImmutable::instance($lockedExtension->proposed_return_at)
                ->timezone(RentalScheduleService::TIMEZONE);

            // Pemeriksaan ketersediaan tahap 1 (SYS-09): pastikan jeda 1 jam dengan pesanan berikutnya aman
            // sebelum pelanggan diminta melakukan transfer biaya perpanjangan.
            $this->scheduleService->releaseExpiredHolds($car->id, $nowWib);
            if (! $this->scheduleService->isCarAvailableForPeriod($car, $lockedRental->pickup_at, $proposedReturnAt, $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Perpanjangan tidak dapat disetujui karena bentrok dengan jadwal pemesanan berikutnya atau jeda 1 jam.',
                ]);
            }

            // Jadwal kembali pesanan ($lockedRental->return_at) sengaja BELUM diubah di sini.
            // Snapshot rekening pembayaran disimpan pada tagihan perpanjangan agar tetap utuh walau rekening diubah (A-13).
            $twoHoursDeadline = $nowWib->addHours(2);
            $paymentDeadline = $twoHoursDeadline->lessThan($proposedReturnAt) ? $twoHoursDeadline : $proposedReturnAt;

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_AWAITING_PAYMENT,
                'approved_by' => $admin->id,
                'approved_at' => $nowWib,
                'payment_deadline_at' => $paymentDeadline,
                'payment_bank_name' => $settings->bank_name,
                'payment_account_number' => $settings->account_number,
                'payment_account_holder' => $settings->account_holder,
                'notes' => $trimmedNotes !== '' ? $trimmedNotes : $lockedExtension->notes,
            ])->save();

            $lockedRental->forceFill([
                'status_notice' => 'Pengajuan perpanjangan sewa disetujui admin. Segera unggah bukti transfer biaya tambahan sebelum batas waktu berakhir.',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_approved',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedNotes !== '' ? $trimmedNotes : 'Pengajuan perpanjangan disetujui; menunggu pembayaran tambahan.',
                [
                    'extension_id' => $lockedExtension->id,
                    'additional_days' => $lockedExtension->additional_days,
                    'additional_amount' => (float) $lockedExtension->additional_amount,
                    'payment_deadline_at' => $paymentDeadline->toIso8601String(),
                ]
            );

            return $lockedExtension->refresh();
        });
    }

    /**
     * Tolak pengajuan perpanjangan oleh admin dengan alasan wajib (A-08).
     *
     * @throws ValidationException
     */
    public function rejectExtension(RentalExtension $extension, User $admin, string $reason): RentalExtension
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Hanya admin yang berhak menolak pengajuan perpanjangan.',
            ]);
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan pengajuan perpanjangan wajib diisi.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($extension, $admin, $trimmedReason, $nowWib): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

            if ($lockedExtension->status !== RentalExtension::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Hanya pengajuan perpanjangan berstatus menunggu persetujuan yang dapat ditolak.',
                ]);
            }

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_REJECTED,
                'approved_by' => $admin->id,
                'approved_at' => $nowWib,
                'rejection_reason' => $trimmedReason,
            ])->save();

            $lockedRental->forceFill([
                'status_notice' => 'Pengajuan perpanjangan ditolak admin: '.$trimmedReason.'. Kembalikan kendaraan sesuai jadwal awal.',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_rejected',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedReason,
                [
                    'extension_id' => $lockedExtension->id,
                ]
            );

            return $lockedExtension->refresh();
        });
    }

    /**
     * Unggah bukti pembayaran biaya tambahan perpanjangan secara privat oleh pelanggan (C-09, NFR-01, NFR-04).
     *
     * @throws ValidationException
     */
    public function submitExtensionPayment(RentalExtension $extension, User $customer, UploadedFile $proofFile): RentalExtension
    {
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $fileExtension = $proofFile->getClientOriginalExtension() ?: 'jpg';
        $storedPath = 'payments/extensions/'.Str::uuid().'.'.$fileExtension;

        // Simpan berkas di disk privat sebelum transaksi DB, dan hapus kembali apabila transaksi gagal.
        if (! Storage::disk('local')->putFileAs('payments/extensions', $proofFile, basename($storedPath))) {
            throw new RuntimeException('Gagal menyimpan bukti pembayaran perpanjangan ke penyimpanan privat.');
        }

        $previousProofPath = null;

        try {
            $updatedExtension = DB::transaction(function () use ($extension, $customer, $storedPath, $nowWib, &$previousProofPath): RentalExtension {
                // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
                $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
                Car::query()->lockForUpdate()->findOrFail($carId);
                $this->releaseExpiredExtensions($carId, $nowWib);

                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
                $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

                if ($lockedRental->user_id !== $customer->id) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Anda tidak berhak mengunggah bukti pembayaran untuk pesanan milik orang lain.',
                    ]);
                }

                if ($lockedExtension->status !== RentalExtension::STATUS_AWAITING_PAYMENT) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Perpanjangan ini tidak berada pada tahap menunggu unggahan bukti pembayaran.',
                    ]);
                }

                if (! in_array($lockedRental->rental_status, [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP], true)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Status penyewaan tidak memungkinkan pembayaran perpanjangan saat ini.',
                    ]);
                }

                if ($lockedExtension->isPaymentExpired($nowWib)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Batas waktu unggah bukti pembayaran perpanjangan telah berakhir.',
                    ]);
                }

                $previousProofPath = $lockedExtension->payment_proof;

                $lockedExtension->forceFill([
                    'payment_proof' => $storedPath,
                    'payment_submitted_at' => $nowWib,
                    'status' => RentalExtension::STATUS_PAYMENT_SUBMITTED,
                    'payment_rejection_reason' => null,
                ])->save();

                $lockedRental->forceFill([
                    'status_notice' => 'Bukti pembayaran perpanjangan telah diterima dan sedang menunggu verifikasi admin.',
                ])->save();

                $lockedRental->recordHistory(
                    $customer,
                    'extension_payment_submitted',
                    $lockedRental->rental_status,
                    $lockedRental->rental_status,
                    null,
                    [
                        'extension_id' => $lockedExtension->id,
                        'additional_amount' => (float) $lockedExtension->additional_amount,
                    ]
                );

                return $lockedExtension->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        if (is_string($previousProofPath) && $previousProofPath !== '' && $previousProofPath !== $storedPath) {
            Storage::disk('local')->delete($previousProofPath);
        }

        return $updatedExtension;
    }

    /**
     * Verifikasi nominal aktual uang masuk perpanjangan (termasuk auto-refund bila lebih bayar) dan aktifkan batas kembali baru (A-08, SYS-09).
     *
     * @throws ValidationException
     */
    public function approveExtensionPayment(
        RentalExtension $extension,
        User $admin,
        float $verifiedAmount,
        ?CarbonInterface $receivedAt = null
    ): RentalExtension {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'extension_id' => 'Hanya admin yang berhak memverifikasi pembayaran perpanjangan.',
            ]);
        }

        if ($receivedAt === null) {
            throw ValidationException::withMessages([
                'received_at' => 'Tanggal aktual dana perpanjangan diterima (WIB) wajib diisi.',
            ]);
        }

        $normalizedVerified = round($verifiedAmount, 2);
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $receivedAtWib = CarbonImmutable::instance($receivedAt)->timezone(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($extension, $admin, $normalizedVerified, $receivedAtWib, $nowWib): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
            $car = Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

            if ($lockedExtension->status !== RentalExtension::STATUS_PAYMENT_SUBMITTED) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Perpanjangan ini belum memiliki bukti pembayaran yang menunggu verifikasi.',
                ]);
            }

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_CONFIRMED, Rental::STATUS_PICKED_UP], true)) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Jadwal perpanjangan hanya dapat diaktifkan pada pesanan berstatus terkonfirmasi atau sedang berjalan.',
                ]);
            }

            $remainingBalance = $lockedExtension->remainingExtensionBalance();
            if ($normalizedVerified < $remainingBalance) {
                throw ValidationException::withMessages([
                    'verified_amount' => 'Nominal terverifikasi kurang dari sisa biaya perpanjangan (Rp '
                        .number_format($remainingBalance, 0, ',', '.')
                        .'). Gunakan penolakan bukti dengan mencatat dana masuk atau batalkan perpanjangan jika jadwal bentrok.',
                ]);
            }

            $proposedReturnAt = CarbonImmutable::instance($lockedExtension->proposed_return_at)
                ->timezone(RentalScheduleService::TIMEZONE);

            // Pemeriksaan ketersediaan tahap 2 (SYS-09): sebelum jadwal kembali baru diaktifkan,
            // sistem memeriksa ulang agar rentang tambahan belum diambil pesanan lain selama menunggu transfer.
            $this->scheduleService->releaseExpiredHolds($car->id, $nowWib);
            if (! $this->scheduleService->isCarAvailableForPeriod($car, $lockedRental->pickup_at, $proposedReturnAt, $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Jadwal perpanjangan kini bentrok dengan pesanan lain yang sudah masuk. Jadwal awal dipertahankan; catat dana masuk lalu batalkan perpanjangan untuk mengembalikan dana.',
                ]);
            }

            $previousReturnAt = $lockedRental->return_at;
            $newTotalDays = $lockedRental->total_days + $lockedExtension->additional_days;
            $newTotalAmount = round((float) $lockedRental->total_amount + (float) $lockedExtension->additional_amount, 2);
            $excessAmount = max(0.0, round($normalizedVerified - $remainingBalance, 2));
            $newRentalPaidAmount = round((float) $lockedRental->paid_amount + $normalizedVerified, 2);
            $newExtensionPaidAmount = round((float) $lockedExtension->paid_amount + $normalizedVerified, 2);

            $notice = 'Pembayaran perpanjangan terverifikasi. Batas pengembalian baru berlaku hingga '.$proposedReturnAt->format('d M Y H:i').' WIB.';
            $rentalOverpaymentUpdate = [];
            $extRefundData = [];

            if ($excessAmount > 0) {
                $extRefundData = [
                    'refund_amount' => $excessAmount,
                    'refund_status' => RentalExtension::REFUND_PENDING,
                    'refund_notes' => 'Kelebihan bayar biaya perpanjangan sewa.',
                ];
                $rentalOverpaymentUpdate = [
                    'overpayment_amount' => round((float) $lockedRental->overpayment_amount + $excessAmount, 2),
                ];
                $notice .= ' Kelebihan bayar perpanjangan sebesar Rp '.number_format($excessAmount, 0, ',', '.').' menunggu pengembalian dana admin.';
            }

            $lockedRental->forceFill([
                'return_at' => $proposedReturnAt,
                'end_date' => $proposedReturnAt->toDateString(),
                'total_days' => $newTotalDays,
                'total_amount' => $newTotalAmount,
                'paid_amount' => $newRentalPaidAmount,
                'status_notice' => $notice,
            ] + $rentalOverpaymentUpdate)->save();

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_COMPLETED,
                'paid_amount' => $newExtensionPaidAmount,
                'verified_amount' => $newExtensionPaidAmount,
                'payment_verified_by' => $admin->id,
                'payment_verified_at' => $nowWib,
                'payment_rejection_reason' => null,
            ] + $extRefundData)->save();

            // Catat kas masuk pelunasan perpanjangan pada tanggal aktual transfer diterima (WIB).
            RentalCashFlow::create([
                'rental_id' => $lockedRental->id,
                'rental_extension_id' => $lockedExtension->id,
                'recorded_by' => $admin->id,
                'direction' => RentalCashFlow::DIRECTION_INFLOW,
                'category' => RentalCashFlow::CATEGORY_EXTENSION_PAYMENT,
                'amount' => $normalizedVerified,
                'occurred_at' => $receivedAtWib,
                'notes' => 'Pelunasan perpanjangan sewa +'.$lockedExtension->additional_days.' hari.',
            ]);

            $lockedRental->recordHistory(
                $admin,
                'extension_activated',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                'Pembayaran perpanjangan sewa terverifikasi dan jadwal kembali diperbarui.',
                [
                    'extension_id' => $lockedExtension->id,
                    'additional_days' => $lockedExtension->additional_days,
                    'verified_amount' => $normalizedVerified,
                    'received_at' => $receivedAtWib->toIso8601String(),
                    'overpayment_amount' => $excessAmount,
                    'additional_amount' => (float) $lockedExtension->additional_amount,
                    'previous_return_at' => $previousReturnAt?->toIso8601String(),
                    'new_return_at' => $proposedReturnAt->toIso8601String(),
                    'new_total_days' => $newTotalDays,
                    'new_total_amount' => $newTotalAmount,
                ]
            );

            return $lockedExtension->refresh();
        });
    }

    /**
     * Tolak bukti pembayaran perpanjangan (untuk unggah ulang dalam 1 jam) atau batalkan perpanjangan dengan refund 100% atas dana masuk (A-08, A-12, A2).
     *
     * @throws ValidationException
     */
    public function rejectExtensionPayment(
        RentalExtension $extension,
        User $admin,
        string $reason,
        float $receivedAmount = 0.0,
        bool $cancelExtension = false,
        ?CarbonInterface $receivedAt = null
    ): RentalExtension {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Hanya admin yang berhak memproses penolakan pembayaran perpanjangan.',
            ]);
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan atau pembatalan perpanjangan wajib diisi.',
            ]);
        }

        $normalizedReceived = round($receivedAmount, 2);
        if ($normalizedReceived < 0) {
            throw ValidationException::withMessages([
                'received_amount' => 'Nominal dana yang diterima tidak boleh bernilai negatif.',
            ]);
        }

        if ($normalizedReceived > 0 && $receivedAt === null) {
            throw ValidationException::withMessages([
                'received_at' => 'Tanggal aktual dana perpanjangan diterima (WIB) wajib diisi apabila terdapat dana masuk.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $receivedAtWib = $receivedAt !== null
            ? CarbonImmutable::instance($receivedAt)->timezone(RentalScheduleService::TIMEZONE)
            : null;

        return DB::transaction(function () use ($extension, $admin, $trimmedReason, $normalizedReceived, $cancelExtension, $receivedAtWib, $nowWib): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

            $allowedStatuses = $cancelExtension
                ? [RentalExtension::STATUS_AWAITING_PAYMENT, RentalExtension::STATUS_PAYMENT_SUBMITTED]
                : [RentalExtension::STATUS_PAYMENT_SUBMITTED];

            if (! in_array($lockedExtension->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Status perpanjangan ini tidak dapat diproses pada tahap penolakan pembayaran.',
                ]);
            }

            $remaining = $lockedExtension->remainingExtensionBalance();

            if (! $cancelExtension && $normalizedReceived >= $remaining) {
                throw ValidationException::withMessages([
                    'received_amount' => 'Nominal dana yang diterima sudah melunasi biaya perpanjangan. Gunakan tombol verifikasi lunas.',
                ]);
            }

            $newPaidAmount = round((float) $lockedExtension->paid_amount + $normalizedReceived, 2);

            if ($normalizedReceived > 0 && $receivedAtWib !== null) {
                // Catat penerimaan dana perpanjangan sebagian/batal pada tanggal dana benar-benar masuk rekening (WIB).
                RentalCashFlow::create([
                    'rental_id' => $lockedRental->id,
                    'rental_extension_id' => $lockedExtension->id,
                    'recorded_by' => $admin->id,
                    'direction' => RentalCashFlow::DIRECTION_INFLOW,
                    'category' => RentalCashFlow::CATEGORY_EXTENSION_PAYMENT,
                    'amount' => $normalizedReceived,
                    'occurred_at' => $receivedAtWib,
                    'notes' => ($cancelExtension ? 'Dana masuk pada pembatalan perpanjangan: ' : 'Pembayaran sebagian perpanjangan saat bukti ditolak: ').$trimmedReason,
                ]);
            }

            if ($cancelExtension) {
                $refundAmount = $newPaidAmount;
                $refundStatus = $refundAmount > 0 ? RentalExtension::REFUND_PENDING : RentalExtension::REFUND_NONE;

                $lockedExtension->forceFill([
                    'status' => RentalExtension::STATUS_REJECTED,
                    'paid_amount' => $newPaidAmount,
                    'verified_amount' => $newPaidAmount,
                    'payment_verified_by' => $admin->id,
                    'payment_verified_at' => $nowWib,
                    'payment_rejection_reason' => $trimmedReason,
                    'refund_amount' => $refundAmount,
                    'refund_status' => $refundStatus,
                ])->save();

                $lockedRental->forceFill([
                    'status_notice' => $refundAmount > 0
                        ? 'Perpanjangan tidak dapat diaktifkan ('.$trimmedReason.'). Hak refund dana perpanjangan sebesar Rp '.number_format($refundAmount, 0, ',', '.').' menunggu transfer admin.'
                        : 'Perpanjangan dibatalkan: '.$trimmedReason.'. Jadwal pengembalian awal tetap berlaku.',
                ])->save();

                $lockedRental->recordHistory(
                    $admin,
                    'extension_cancelled_with_refund',
                    $lockedRental->rental_status,
                    $lockedRental->rental_status,
                    $trimmedReason,
                    [
                        'extension_id' => $lockedExtension->id,
                        'received_amount' => $normalizedReceived,
                        'received_at' => $receivedAtWib?->toIso8601String(),
                        'total_extension_paid' => $newPaidAmount,
                        'refund_amount' => $refundAmount,
                        'refund_status' => $refundStatus,
                    ]
                );

                return $lockedExtension->refresh();
            }

            $proposedReturnAt = CarbonImmutable::instance($lockedExtension->proposed_return_at)
                ->timezone(RentalScheduleService::TIMEZONE);
            $oneHourFromRejection = $nowWib->addHour();
            $fixDeadline = $oneHourFromRejection->lessThan($proposedReturnAt) ? $oneHourFromRejection : $proposedReturnAt;

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_AWAITING_PAYMENT,
                'paid_amount' => $newPaidAmount,
                'verified_amount' => $newPaidAmount > 0 ? $newPaidAmount : $lockedExtension->verified_amount,
                'payment_deadline_at' => $fixDeadline,
                'payment_verified_by' => $admin->id,
                'payment_verified_at' => $nowWib,
                'payment_rejection_reason' => $trimmedReason,
            ])->save();

            $newRemaining = $lockedExtension->remainingExtensionBalance();
            $lockedRental->forceFill([
                'status_notice' => 'Bukti pembayaran perpanjangan ditolak admin: '.$trimmedReason
                    .'. Sisa tagihan: Rp '.number_format($newRemaining, 0, ',', '.')
                    .'. Unggah bukti perbaikan sebelum '.$fixDeadline->format('d M Y H:i').' WIB.',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_payment_rejected',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedReason,
                [
                    'extension_id' => $lockedExtension->id,
                    'received_amount' => $normalizedReceived,
                    'received_at' => $receivedAtWib?->toIso8601String(),
                    'remaining_balance' => $newRemaining,
                    'fix_deadline_at' => $fixDeadline->toIso8601String(),
                ]
            );

            return $lockedExtension->refresh();
        });
    }

    /**
     * Lepaskan pengajuan perpanjangan yang melewati batas waktu pembayaran dan rekonsiliasi dana parsial bila ada (SYS-06, SYS-12).
     */
    public function releaseExpiredExtensions(?int $carId = null, ?CarbonInterface $at = null): int
    {
        $reference = $at
            ? CarbonImmutable::instance($at)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $cutoff = $reference->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($carId, $cutoff): int {
            $expiredQuery = RentalExtension::query()
                ->where('status', RentalExtension::STATUS_AWAITING_PAYMENT)
                ->whereNotNull('payment_deadline_at')
                ->where('payment_deadline_at', '<=', $cutoff)
                ->when($carId !== null, function ($q) use ($carId): void {
                    $q->whereHas('rental', fn ($rq) => $rq->where('car_id', $carId));
                });

            $rentalIds = (clone $expiredQuery)->orderBy('rental_id')->pluck('rental_id')->unique()->values()->all();
            if ($rentalIds === []) {
                return 0;
            }

            // Ikuti urutan kunci global: cars ASC -> rentals ASC -> rental_extensions ASC.
            $carIds = Rental::query()->whereIn('id', $rentalIds)->orderBy('car_id')->pluck('car_id')->unique()->values()->all();
            if ($carIds !== []) {
                Car::query()->whereIn('id', $carIds)->orderBy('id')->lockForUpdate()->get();
            }

            $lockedRentals = Rental::query()->whereIn('id', $rentalIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $expiredExtensions = $expiredQuery->orderBy('id')->lockForUpdate()->get();

            $updatedCount = 0;

            foreach ($expiredExtensions as $extension) {
                $paidAmount = max(0.0, round((float) $extension->paid_amount, 2));
                $refundStatus = $paidAmount > 0 ? RentalExtension::REFUND_PENDING : RentalExtension::REFUND_NONE;

                $extension->forceFill([
                    'status' => RentalExtension::STATUS_EXPIRED,
                    'refund_amount' => $paidAmount,
                    'refund_status' => $refundStatus,
                    'payment_rejection_reason' => $extension->payment_rejection_reason
                        ?: 'Batas waktu pembayaran perpanjangan telah berakhir.',
                ])->save();

                $rental = $lockedRentals->get($extension->rental_id);
                if ($rental instanceof Rental) {
                    $notice = $paidAmount > 0
                        ? 'Pengajuan perpanjangan kedaluwarsa. Dana masuk sebesar Rp '.number_format($paidAmount, 0, ',', '.').' ditetapkan sebagai refund 100% menunggu transfer admin.'
                        : 'Pengajuan perpanjangan melewati batas waktu pembayaran sehingga otomatis kedaluwarsa.';

                    $rental->forceFill(['status_notice' => $notice])->save();
                    $rental->recordHistory(
                        null,
                        'extension_expired',
                        RentalExtension::STATUS_AWAITING_PAYMENT,
                        RentalExtension::STATUS_EXPIRED,
                        'Melewati batas waktu pembayaran perpanjangan.',
                        [
                            'extension_id' => $extension->id,
                            'paid_amount' => $paidAmount,
                            'refund_amount' => $paidAmount,
                            'refund_status' => $refundStatus,
                        ]
                    );
                }

                $updatedCount++;
            }

            return $updatedCount;
        });
    }

    /**
     * Catat realisasi pengembalian dana perpanjangan yang batal, kedaluwarsa, atau berlebih bayar (A2, SYS-12).
     *
     * @throws ValidationException
     */
    public function realizeExtensionRefund(
        RentalExtension $extension,
        User $admin,
        float $transferAmount,
        CarbonInterface $transferredAt,
        string $reference,
        ?string $notes = null
    ): RentalExtension {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'extension_refund' => 'Hanya admin yang berhak mencatat realisasi refund perpanjangan.',
            ]);
        }

        $trimmedReference = trim($reference);
        if ($trimmedReference === '') {
            throw ValidationException::withMessages([
                'refund_reference' => 'Nomor referensi transfer refund perpanjangan wajib diisi.',
            ]);
        }

        $normalizedAmount = round($transferAmount, 2);

        return DB::transaction(function () use ($extension, $admin, $normalizedAmount, $transferredAt, $trimmedReference, $notes): RentalExtension {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension.
            $carId = (int) Rental::query()->whereKey($extension->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($extension->rental_id);
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);

            $refundableStatuses = [
                RentalExtension::STATUS_REJECTED,
                RentalExtension::STATUS_EXPIRED,
                RentalExtension::STATUS_COMPLETED,
            ];

            if (! in_array($lockedExtension->status, $refundableStatuses, true)
                || $lockedExtension->refund_status !== RentalExtension::REFUND_PENDING) {
                throw ValidationException::withMessages([
                    'extension_refund' => 'Perpanjangan ini tidak memiliki kewajiban refund berstatus menunggu transfer.',
                ]);
            }

            $paidAmount = max(0.0, round((float) $lockedExtension->paid_amount, 2));
            $entitledRefund = max(0.0, round((float) $lockedExtension->refund_amount, 2));

            if ($normalizedAmount <= 0 || $normalizedAmount > $paidAmount || $normalizedAmount !== $entitledRefund) {
                throw ValidationException::withMessages([
                    'transfer_amount' => 'Nominal transfer refund perpanjangan harus sama persis dengan hak refund Rp '
                        .number_format($entitledRefund, 0, ',', '.')
                        .' dan tidak melebihi dana masuk Rp '
                        .number_format($paidAmount, 0, ',', '.').'.',
                ]);
            }

            $transferredAtWib = CarbonImmutable::instance($transferredAt)->timezone(RentalScheduleService::TIMEZONE);

            $lockedExtension->forceFill([
                'refund_amount' => $normalizedAmount,
                'refund_status' => RentalExtension::REFUND_PROCESSED,
                'refund_transferred_at' => $transferredAtWib,
                'refund_reference' => $trimmedReference,
                'refund_notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
                'refund_processed_by' => $admin->id,
            ])->save();

            // Catat kas keluar refund perpanjangan pada tanggal transfer refund direalisasikan (WIB) untuk laporan A-12.
            RentalCashFlow::create([
                'rental_id' => $lockedRental->id,
                'rental_extension_id' => $lockedExtension->id,
                'recorded_by' => $admin->id,
                'direction' => RentalCashFlow::DIRECTION_OUTFLOW,
                'category' => RentalCashFlow::CATEGORY_EXTENSION_REFUND,
                'amount' => $normalizedAmount,
                'occurred_at' => $transferredAtWib,
                'reference' => $trimmedReference,
                'notes' => $lockedExtension->refund_notes,
            ]);

            $lockedRental->forceFill([
                'status_notice' => 'Pengembalian dana perpanjangan sebesar Rp '.number_format($normalizedAmount, 0, ',', '.')
                    .' telah ditransfer (Ref: '.$trimmedReference.').',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_refund_realized',
                RentalExtension::REFUND_PENDING,
                RentalExtension::REFUND_PROCESSED,
                'Transfer pengembalian dana perpanjangan direalisasikan.',
                [
                    'extension_id' => $lockedExtension->id,
                    'refund_amount' => $normalizedAmount,
                    'refund_reference' => $trimmedReference,
                    'refund_transferred_at' => $transferredAtWib->toIso8601String(),
                ]
            );

            return $lockedExtension->refresh();
        });
    }

    /**
     * Periksa apakah masih terdapat pengajuan perpanjangan yang aktif pada pesanan ini.
     */
    private function hasOpenExtension(Rental $rental): bool
    {
        return RentalExtension::query()
            ->where('rental_id', $rental->id)
            ->whereIn('status', [
                RentalExtension::STATUS_PENDING_APPROVAL,
                RentalExtension::STATUS_AWAITING_PAYMENT,
                RentalExtension::STATUS_PAYMENT_SUBMITTED,
            ])
            ->exists();
    }
}
