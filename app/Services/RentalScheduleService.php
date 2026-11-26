<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RentalScheduleService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const BUFFER_MINUTES = 60;

    public const HOURS_PER_DAY = 24;

    public const MAX_DAYS = 365;

    public const PAYMENT_WINDOW_HOURS = 2;

    /**
     * Parse a pickup date/time input strictly in WIB (Asia/Jakarta).
     */
    public function parsePickupAt(string $pickupInput): CarbonImmutable
    {
        // CarbonImmutable::parse(..., TIMEZONE) menafsirkan string tanggal/jam masukan
        // memakai zona waktu WIB (Asia/Jakarta) tanpa mengubah objek tanggal asli saat dihitung.
        return CarbonImmutable::parse($pickupInput, self::TIMEZONE)->timezone(self::TIMEZONE);
    }

    /**
     * Calculate exact return timestamp based on 24 hours per rental day (SYS-03).
     */
    public function calculateReturnAt(CarbonInterface $pickupAt, int $totalDays): CarbonImmutable
    {
        // addHours() menambahkan tepat 24 jam untuk setiap hari sewa sesuai kebijakan SKPL
        // sehingga durasi 1 hari berakhir pada jam yang sama keesokan harinya (WIB).
        return CarbonImmutable::instance($pickupAt)
            ->timezone(self::TIMEZONE)
            ->addHours($totalDays * self::HOURS_PER_DAY);
    }

    /**
     * Compute authoritative schedule dates, unit price snapshot, and total cost (SYS-03).
     *
     * @return array{
     *     pickup_at: CarbonImmutable,
     *     return_at: CarbonImmutable,
     *     start_date: string,
     *     end_date: string,
     *     total_days: int,
     *     daily_price: float,
     *     total_amount: float,
     *     buffer_minutes: int
     * }
     */
    public function calculatePricing(Car $car, CarbonInterface $pickupAt, int $totalDays): array
    {
        $pickupWib = CarbonImmutable::instance($pickupAt)->timezone(self::TIMEZONE);
        $returnWib = $this->calculateReturnAt($pickupWib, $totalDays);
        $dailyPrice = round((float) $car->price_per_day, 2);
        $totalAmount = round($dailyPrice * $totalDays, 2);

        return [
            'pickup_at' => $pickupWib,
            'return_at' => $returnWib,
            'start_date' => $pickupWib->toDateString(),
            'end_date' => $returnWib->toDateString(),
            'total_days' => $totalDays,
            'daily_price' => $dailyPrice,
            'total_amount' => $totalAmount,
            'buffer_minutes' => self::BUFFER_MINUTES,
        ];
    }

    /**
     * Calculate upload payment deadline: maximum 2 hours from booking without exceeding pickup_at (SYS-06).
     */
    public function calculatePaymentDeadline(CarbonInterface $bookedAt, CarbonInterface $pickupAt): CarbonImmutable
    {
        $twoHoursAfterBooking = CarbonImmutable::instance($bookedAt)
            ->timezone(self::TIMEZONE)
            ->addHours(self::PAYMENT_WINDOW_HOURS);
        $pickupWib = CarbonImmutable::instance($pickupAt)->timezone(self::TIMEZONE);

        // min() memastikan batas unggah bukti pembayaran tidak melewati waktu pengambilan kendaraan.
        return $twoHoursAfterBooking->lessThan($pickupWib) ? $twoHoursAfterBooking : $pickupWib;
    }

    /**
     * Check whether a car has any overlapping or 1-hour-buffer conflicting hold (SYS-04).
     */
    public function hasScheduleConflict(
        int $carId,
        CarbonInterface $pickupAt,
        CarbonInterface $returnAt,
        ?int $ignoreRentalId = null
    ): bool {
        // Jeda minimal 1 jam diterapkan pada kedua sisi periode sewa:
        // pesanan baru hanya aman jika pesanan lama berakhir <= pickupAt - 60 menit
        // atau dimulai >= returnAt + 60 menit.
        $windowStartWithBuffer = CarbonImmutable::instance($pickupAt)
            ->timezone(self::TIMEZONE)
            ->subMinutes(self::BUFFER_MINUTES)
            ->format('Y-m-d H:i:s');
        $windowEndWithBuffer = CarbonImmutable::instance($returnAt)
            ->timezone(self::TIMEZONE)
            ->addMinutes(self::BUFFER_MINUTES)
            ->format('Y-m-d H:i:s');

        return Rental::query()
            ->where('car_id', $carId)
            ->when($ignoreRentalId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreRentalId))
            ->holdingSchedule()
            ->whereRaw('COALESCE(pickup_at, start_date::timestamp) < ?', [$windowEndWithBuffer])
            ->whereRaw('COALESCE(return_at, end_date::timestamp) > ?', [$windowStartWithBuffer])
            ->exists();
    }

    /**
     * Determine whether a specific vehicle is bookable and free for the requested period.
     */
    public function isCarAvailableForPeriod(
        Car $car,
        CarbonInterface $pickupAt,
        CarbonInterface $returnAt,
        ?int $ignoreRentalId = null
    ): bool {
        if (! $car->isBookableForSchedule()) {
            return false;
        }

        return ! $this->hasScheduleConflict($car->id, $pickupAt, $returnAt, $ignoreRentalId);
    }

    /**
     * Build the shared query of vehicles available for a period across web and API (SYS-04, SYS-15).
     *
     * @param  array{search?: ?string, car_type_id?: mixed, transmission?: ?string}  $filters
     */
    public function availableCarsQuery(
        CarbonInterface $pickupAt,
        CarbonInterface $returnAt,
        array $filters = []
    ): Builder {
        $windowStartWithBuffer = CarbonImmutable::instance($pickupAt)
            ->timezone(self::TIMEZONE)
            ->subMinutes(self::BUFFER_MINUTES)
            ->format('Y-m-d H:i:s');
        $windowEndWithBuffer = CarbonImmutable::instance($returnAt)
            ->timezone(self::TIMEZONE)
            ->addMinutes(self::BUFFER_MINUTES)
            ->format('Y-m-d H:i:s');

        // bookableForSchedule() mengizinkan unit aktif (termasuk yang sedang disewa sekarang)
        // untuk dipesan pada periode mendatang, kecuali unit arsip atau dalam perawatan.
        // whereDoesntHave('rentals', ...) menolak mobil yang memiliki pesanan penahan jadwal
        // yang bertumpuk maupun masuk dalam jeda persiapan 60 menit.
        $query = Car::bookableForSchedule()
            ->with('carType')
            ->whereDoesntHave('rentals', function (Builder $rentalQuery) use ($windowStartWithBuffer, $windowEndWithBuffer): void {
                $rentalQuery->holdingSchedule()
                    ->whereRaw('COALESCE(pickup_at, start_date::timestamp) < ?', [$windowEndWithBuffer])
                    ->whereRaw('COALESCE(return_at, end_date::timestamp) > ?', [$windowStartWithBuffer]);
            });

        if (isset($filters['search']) && is_string($filters['search']) && trim($filters['search']) !== '') {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['car_type_id']) && is_numeric($filters['car_type_id'])) {
            $query->where('car_type_id', (int) $filters['car_type_id']);
        }

        if (isset($filters['transmission']) && in_array($filters['transmission'], ['manual', 'automatic'], true)) {
            $query->where('transmission', $filters['transmission']);
        }

        return $query;
    }

    public static function reviewOverdue(?CarbonInterface $submittedAt, CarbonInterface $at): bool
    {
        return $submittedAt !== null && $submittedAt->lte(self::reviewCutoff($at));
    }

    /**
     * Hitung mundur satu jam kerja. Cutoff yang sama dapat dipakai pada filter
     * SQL dan peringatan detail tanpa membaca setiap baris untuk menghitung SLA.
     */
    public static function reviewCutoff(CarbonInterface $at): CarbonImmutable
    {
        $now = CarbonImmutable::instance($at)->timezone(self::TIMEZONE);
        // Jam operasional disimpan sebagai teks. Baca rentang jam yang biasa
        // dipakai setting, dengan default usaha bila teks tidak memuat rentang.
        $hours = RentalSetting::publicInfo()->operating_hours;
        $open = 480;
        $close = 1260;
        if (preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\s*[-–—]\s*([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', $hours, $match)) {
            $open = (int) $match[1] * 60 + (int) $match[2];
            $close = (int) $match[3] * 60 + (int) $match[4];
        }
        if ($close <= $open) {
            $close += 1440;
        }

        $day = $now->startOfDay();
        $remaining = 3600;
        while (true) {
            $start = $day->addMinutes($open);
            $end = $day->addMinutes($close)->min($now);
            if ($end->gt($start)) {
                $seconds = (int) $start->diffInSeconds($end);
                if ($remaining <= $seconds) {
                    return $end->subSeconds($remaining);
                }
                $remaining -= $seconds;
            }
            $day = $day->subDay();
        }
    }

    /**
     * Lepas penahanan yang sudah gagal dan catat tindak lanjut pemeriksaan.
     */
    public function releaseExpiredHolds(?int $carId = null, ?CarbonInterface $at = null): int
    {
        $reference = $at ? CarbonImmutable::instance($at)->timezone(self::TIMEZONE) : CarbonImmutable::now(self::TIMEZONE);
        $cutoff = $reference->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($carId, $cutoff, $reference): int {
            $candidates = Rental::query()->where('rental_status', Rental::STATUS_PENDING)
                ->where(function (Builder $due) use ($cutoff): void {
                    $due->where(fn (Builder $payment) => $payment->where('payment_review_blocked', false)
                        ->whereIn('payment_status', [Rental::PAYMENT_UNPAID, Rental::PAYMENT_REJECTED])
                        ->where('payment_deadline_at', '<=', $cutoff))
                        ->orWhere(fn (Builder $identity) => $identity->where('identity_status', Rental::IDENTITY_REJECTED)
                            ->where('identity_deadline_at', '<=', $cutoff))
                        ->orWhere(fn (Builder $followup) => $followup->whereNotNull('identity_status')
                            ->whereNull('review_followup_at')->where('pickup_at', '<=', $cutoff));
                });

            // Kunci kendaraan dulu dan baca ulang kandidat setelah penguncian.
            // Approval/unggah bersamaan akan terlihat sebelum status diubah.
            $carIds = $carId !== null ? [$carId] : (clone $candidates)->distinct()->orderBy('car_id')->pluck('car_id')->all();
            Car::query()->whereIn('id', $carIds)->orderBy('id')->lockForUpdate()->get();
            $rentals = $candidates->whereIn('car_id', $carIds)->orderBy('id')->lockForUpdate()->get();
            $expiredCount = 0;

            foreach ($rentals as $rental) {
                $paymentExpired = $rental->isPaymentExpired($reference);
                $identityExpired = $rental->isIdentityExpired($reference);
                if (! $paymentExpired && ! $identityExpired) {
                    $rental->review_followup_at = $reference;
                    $rental->saveOrFail();
                    $rental->recordHistory(null, 'admin_review_followup', null, null,
                        'Jadwal ambil tiba saat pemeriksaan belum selesai. Hubungi pelanggan.');

                    continue;
                }

                // Jika kedua kewajiban gagal, pakai waktu paling awal. Operator
                // <= memberi prioritas identitas saat kedua waktu sama.
                $identityFirst = $identityExpired && (! $paymentExpired
                    || $rental->identity_deadline_at->lte($rental->payment_deadline_at));
                $type = $identityFirst ? Rental::CANCEL_IDENTITY : Rental::CANCEL_PAYMENT_EXPIRED;
                $status = $identityFirst ? Rental::STATUS_CANCELLED : Rental::STATUS_EXPIRED;
                $reason = $identityFirst ? 'Tenggat perbaikan dokumen identitas terlewati.'
                    : 'Melewati batas waktu pembayaran tanpa bukti transfer yang valid.';

                $rental->transitionRentalStatus($status, $reason.' Jadwal kendaraan dilepas.');
                $rental->cancelled_at = $reference;
                $rental->cancellation_type = $type;
                $rental->cancellation_reason = $reason;
                app(RentalFinancialService::class)->refreshCancellationRefund($rental);
                $rental->saveOrFail();
                $rental->recordHistory(null, $identityFirst ? 'booking_identity_cancelled' : 'booking_expired',
                    Rental::STATUS_PENDING, $status, $reason, [
                        'cancellation_type' => $type,
                        'refund_amount' => (float) $rental->refund_amount,
                    ]);
                $expiredCount++;
            }

            return $expiredCount;
        });
    }
}
