<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
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
     * Determine whether a specific vehicle is ready and free for the requested period.
     */
    public function isCarAvailableForPeriod(
        Car $car,
        CarbonInterface $pickupAt,
        CarbonInterface $returnAt,
        ?int $ignoreRentalId = null
    ): bool {
        if (! $car->isReadyForRental()) {
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

        // readyForRental() menyaring hanya unit aktif (bukan arsip) berstatus available.
        // whereDoesntHave('rentals', ...) menolak mobil yang memiliki pesanan penahan jadwal
        // yang bertumpuk maupun masuk dalam jeda persiapan 60 menit.
        $query = Car::readyForRental()
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

    /**
     * Release expired unpaid holds without deleting transaction history (SYS-05, SYS-06).
     */
    public function releaseExpiredHolds(?int $carId = null, ?CarbonInterface $at = null): int
    {
        $reference = $at ? CarbonImmutable::instance($at)->timezone(self::TIMEZONE) : CarbonImmutable::now(self::TIMEZONE);
        $cutoff = $reference->format('Y-m-d H:i:s');

        // DB::transaction() bersama lockForUpdate() membuat proses kedaluwarsa bersifat
        // idempoten: eksekusi berulang hanya mengubah pesanan yang masih pending dan unpaid.
        return DB::transaction(function () use ($carId, $cutoff, $reference): int {
            $expiredRentals = Rental::query()
                ->when($carId !== null, fn (Builder $q) => $q->where('car_id', $carId))
                ->where('rental_status', Rental::STATUS_PENDING)
                ->where('payment_status', Rental::PAYMENT_UNPAID)
                ->whereNull('payment_proof')
                ->whereNotNull('payment_deadline_at')
                ->where('payment_deadline_at', '<=', $cutoff)
                ->lockForUpdate()
                ->get();

            $updatedCount = 0;

            foreach ($expiredRentals as $rental) {
                $rental->transitionRentalStatus(
                    Rental::STATUS_EXPIRED,
                    'Batas waktu unggah bukti pembayaran terlewati sehingga jadwal kendaraan dilepas otomatis.'
                );
                $rental->cancelled_at = $reference;
                $rental->cancellation_reason = 'Melewati batas waktu pembayaran 2 jam tanpa mengirim bukti transfer.';
                $rental->save();
                $updatedCount++;
            }

            return $updatedCount;
        });
    }
}
