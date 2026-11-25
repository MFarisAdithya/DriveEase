<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RentalOperationService
{
    public function __construct(
        private readonly RentalFinancialService $financialService,
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Evaluasi apakah seluruh syarat serah-terima kendaraan terpenuhi saat ini (SYS-08, S-02, S-03).
     *
     * @return array{ready: bool, reasons: list<string>}
     */
    public function evaluateHandoverReadiness(Rental $rental, ?CarbonInterface $at = null): array
    {
        $rental->loadMissing(['car', 'user', 'pickupInspection']);
        $reference = $at
            ? CarbonImmutable::instance($at)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $reasons = [];

        if ($rental->rental_status !== Rental::STATUS_CONFIRMED) {
            $reasons[] = 'Pesanan harus berada pada status terkonfirmasi sebelum kendaraan diserahkan.';
        }

        if ($rental->payment_status !== Rental::PAYMENT_PAID || $rental->remainingBalance() > 0) {
            $reasons[] = 'Pembayaran utama pesanan harus lunas dan terverifikasi admin.';
        }

        if (! ($rental->user?->isVerified() ?? false)) {
            $reasons[] = 'Identitas KTP/SIM penyewa harus terverifikasi sebelum serah-terima.';
        }

        if (! ($rental->car?->isReadyForHandover() ?? false)) {
            $reasons[] = 'Unit kendaraan sedang tidak berstatus tersedia atau belum dinyatakan layak siap serah-terima.';
        }

        $hasActiveTrip = Rental::query()
            ->where('car_id', $rental->car_id)
            ->where('id', '!=', $rental->id)
            ->where('rental_status', Rental::STATUS_PICKED_UP)
            ->exists();
        if ($hasActiveTrip) {
            $reasons[] = 'Unit kendaraan masih sedang digunakan oleh transaksi sewa lain yang belum dikembalikan.';
        }

        if ($rental->pickupInspection !== null) {
            $reasons[] = 'Inspeksi pengambilan awal untuk pesanan ini sudah pernah dicatat.';
        }

        if ($rental->return_at === null || $reference->greaterThanOrEqualTo(CarbonImmutable::instance($rental->return_at)->timezone(RentalScheduleService::TIMEZONE))) {
            $reasons[] = 'Batas pengembalian pesanan sudah terlewati sehingga serah-terima memerlukan penjadwalan ulang atau pembatalan.';
        }

        return [
            'ready' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Konfirmasi pengambilan kendaraan dan simpan inspeksi awal secara privat (S-03, SYS-08, SYS-13, NFR-04).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function confirmPickup(Rental $rental, User $staff, array $payload, UploadedFile $photo): Inspection
    {
        if (! $staff->isStaff() && ! $staff->isAdmin()) {
            throw ValidationException::withMessages([
                'handover' => 'Hanya staf operasional atau admin yang dapat mencatat serah-terima kendaraan.',
            ]);
        }

        if (! ((bool) ($payload['identity_checked'] ?? false))) {
            throw ValidationException::withMessages([
                'identity_checked' => 'Petugas wajib memastikan dan mencentang pemeriksaan kesesuaian identitas asli penyewa.',
            ]);
        }

        $odometerKm = (int) ($payload['odometer_km'] ?? -1);
        if ($odometerKm < 0) {
            throw ValidationException::withMessages([
                'odometer_km' => 'Angka kilometer awal tidak boleh negatif.',
            ]);
        }

        $fuelLevel = (string) ($payload['fuel_level'] ?? '');
        if (! array_key_exists($fuelLevel, Inspection::FUEL_LEVEL_LABELS)) {
            throw ValidationException::withMessages([
                'fuel_level' => 'Tingkat bahan bakar awal tidak valid.',
            ]);
        }

        $actualPickupInput = trim((string) ($payload['actual_pickup_at'] ?? ''));
        $actualPickupAt = $actualPickupInput !== ''
            ? $this->scheduleService->parsePickupAt($actualPickupInput)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $conditionNotes = trim((string) ($payload['condition_notes'] ?? ''));

        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $storedPath = 'inspections/pickup/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->putFileAs('inspections/pickup', $photo, basename($storedPath))) {
            throw new RuntimeException('Gagal menyimpan foto inspeksi pengambilan ke penyimpanan privat.');
        }

        try {
            return DB::transaction(function () use ($rental, $staff, $odometerKm, $fuelLevel, $actualPickupAt, $conditionNotes, $storedPath): Inspection {
                // Ikuti urutan kunci global: Car -> Rental -> Inspection.
                $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $lockedRental = Rental::query()->with(['user', 'pickupInspection'])->lockForUpdate()->findOrFail($rental->id);
                $lockedRental->setRelation('car', $car);

                $existingPickup = Inspection::query()
                    ->where('rental_id', $lockedRental->id)
                    ->where('inspection_type', Inspection::TYPE_PICKUP)
                    ->lockForUpdate()
                    ->first();
                $lockedRental->setRelation('pickupInspection', $existingPickup);

                $readiness = $this->evaluateHandoverReadiness($lockedRental, $actualPickupAt);
                if (! $readiness['ready']) {
                    throw ValidationException::withMessages([
                        'handover' => implode(' ', $readiness['reasons']),
                    ]);
                }

                $agreedReturnAt = CarbonImmutable::instance($lockedRental->return_at)->timezone(RentalScheduleService::TIMEZONE);
                if ($actualPickupAt->greaterThanOrEqualTo($agreedReturnAt)) {
                    throw ValidationException::withMessages([
                        'actual_pickup_at' => 'Waktu penyerahan aktual tidak boleh melewati batas waktu pengembalian yang disepakati.',
                    ]);
                }

                $inspection = Inspection::create([
                    'rental_id' => $lockedRental->id,
                    'inspector_id' => $staff->id,
                    'inspection_type' => Inspection::TYPE_PICKUP,
                    'identity_checked' => true,
                    'odometer_km' => $odometerKm,
                    'fuel_level' => $fuelLevel,
                    'condition_notes' => $conditionNotes !== '' ? $conditionNotes : 'Kondisi fisik awal baik dan lengkap sesuai daftar periksa.',
                    'condition_photo' => $storedPath,
                    'has_damage' => false,
                    'penalty_fee' => 0,
                    'inspected_at' => $actualPickupAt,
                ]);

                $previousStatus = $lockedRental->rental_status;

                // Catat waktu serah-terima aktual tanpa mengubah return_at yang disepakati saat pemesanan (SYS-03).
                $lockedRental->recordActualPickup($actualPickupAt);
                $lockedRental->transitionRentalStatus(
                    Rental::STATUS_PICKED_UP,
                    'Kendaraan telah diserahkan pada '.$actualPickupAt->format('d M Y H:i').' WIB. Batas kembali tetap '
                    .$agreedReturnAt->format('d M Y H:i').' WIB.'
                );
                $lockedRental->saveOrFail();

                $car->forceFill([
                    'status' => 'rented',
                    'readiness_status' => Car::READINESS_IN_USE,
                    'readiness_notes' => 'Kendaraan diserahkan untuk transaksi '.$lockedRental->rental_code.'.',
                    'readiness_checked_by' => $staff->id,
                    'readiness_checked_at' => $actualPickupAt,
                ])->saveOrFail();

                $lockedRental->recordHistory(
                    $staff,
                    'vehicle_picked_up',
                    $previousStatus,
                    Rental::STATUS_PICKED_UP,
                    'Serah-terima kendaraan dicatat dengan inspeksi awal.',
                    [
                        'inspection_id' => $inspection->id,
                        'odometer_km' => $odometerKm,
                        'fuel_level' => $fuelLevel,
                        'actual_pickup_at' => $actualPickupAt->toIso8601String(),
                        'agreed_return_at' => $agreedReturnAt->toIso8601String(),
                    ]
                );

                return $inspection;
            });
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            Storage::disk('local')->delete($storedPath);

            $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
            if ((string) $sqlState === '23505') {
                throw ValidationException::withMessages([
                    'handover' => 'Penyerahan kendaraan untuk pesanan ini sudah pernah dicatat dan tidak dapat dilakukan ulang.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * Catat pengembalian kendaraan, inspeksi akhir, hitung denda telat, dan perbarui kesiapan unit (S-04, SYS-10).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function confirmReturn(Rental $rental, User $staff, array $payload, UploadedFile $photo): Inspection
    {
        if (! $staff->isStaff() && ! $staff->isAdmin()) {
            throw ValidationException::withMessages([
                'return' => 'Hanya staf operasional atau admin yang dapat mencatat pengembalian kendaraan.',
            ]);
        }

        $odometerKm = (int) ($payload['odometer_km'] ?? -1);
        if ($odometerKm < 0) {
            throw ValidationException::withMessages([
                'odometer_km' => 'Angka kilometer akhir tidak boleh negatif.',
            ]);
        }

        $fuelLevel = (string) ($payload['fuel_level'] ?? '');
        if (! array_key_exists($fuelLevel, Inspection::FUEL_LEVEL_LABELS)) {
            throw ValidationException::withMessages([
                'fuel_level' => 'Tingkat bahan bakar akhir tidak valid.',
            ]);
        }

        $hasDamage = (bool) ($payload['has_damage'] ?? false);
        $damageDescription = trim((string) ($payload['damage_description'] ?? ''));
        if ($hasDamage && mb_strlen($damageDescription) < 5) {
            throw ValidationException::withMessages([
                'damage_description' => 'Deskripsi kerusakan wajib diisi minimal 5 karakter apabila kendaraan mengalami kerusakan.',
            ]);
        }

        $actualReturnInput = trim((string) ($payload['actual_return_at'] ?? ''));
        $actualReturnAt = $actualReturnInput !== ''
            ? $this->scheduleService->parsePickupAt($actualReturnInput)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $conditionNotes = trim((string) ($payload['condition_notes'] ?? ''));

        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $storedPath = 'inspections/return/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->putFileAs('inspections/return', $photo, basename($storedPath))) {
            throw new RuntimeException('Gagal menyimpan foto inspeksi pengembalian ke penyimpanan privat.');
        }

        try {
            return DB::transaction(function () use ($rental, $staff, $odometerKm, $fuelLevel, $hasDamage, $damageDescription, $actualReturnAt, $conditionNotes, $storedPath): Inspection {
                // Ikuti urutan kunci global: Car -> Rental -> Inspection.
                $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

                if ($lockedRental->rental_status !== Rental::STATUS_PICKED_UP) {
                    throw ValidationException::withMessages([
                        'return' => 'Pengembalian hanya dapat dicatat untuk pesanan yang sedang berstatus berjalan (picked_up).',
                    ]);
                }

                $pickupInspection = Inspection::query()
                    ->where('rental_id', $lockedRental->id)
                    ->where('inspection_type', Inspection::TYPE_PICKUP)
                    ->lockForUpdate()
                    ->first();

                if ($pickupInspection === null) {
                    throw ValidationException::withMessages([
                        'return' => 'Data inspeksi pengambilan awal belum tersedia.',
                    ]);
                }

                $existingReturn = Inspection::query()
                    ->where('rental_id', $lockedRental->id)
                    ->where('inspection_type', Inspection::TYPE_RETURN)
                    ->lockForUpdate()
                    ->first();

                if ($existingReturn !== null) {
                    throw ValidationException::withMessages([
                        'return' => 'Inspeksi pengembalian untuk pesanan ini sudah pernah dicatat.',
                    ]);
                }

                // Sesuai kriteria S-04: kilometer akhir tidak boleh lebih rendah daripada kilometer pengambilan.
                if ($odometerKm < $pickupInspection->odometer_km) {
                    throw ValidationException::withMessages([
                        'odometer_km' => 'Kilometer akhir ('.$odometerKm.' km) tidak boleh lebih rendah daripada kilometer pengambilan awal ('.$pickupInspection->odometer_km.' km).',
                    ]);
                }

                $actualPickupWib = $lockedRental->actual_pickup_at
                    ? CarbonImmutable::instance($lockedRental->actual_pickup_at)->timezone(RentalScheduleService::TIMEZONE)
                    : CarbonImmutable::instance($lockedRental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);

                if ($actualReturnAt->lessThan($actualPickupWib)) {
                    throw ValidationException::withMessages([
                        'actual_return_at' => 'Waktu pengembalian aktual tidak boleh lebih awal dari waktu pengambilan kendaraan.',
                    ]);
                }

                $penalty = $this->financialService->calculateLatePenalty($lockedRental, $actualReturnAt);

                $inspection = Inspection::create([
                    'rental_id' => $lockedRental->id,
                    'inspector_id' => $staff->id,
                    'inspection_type' => Inspection::TYPE_RETURN,
                    'identity_checked' => true,
                    'odometer_km' => $odometerKm,
                    'fuel_level' => $fuelLevel,
                    'condition_notes' => $conditionNotes !== ''
                        ? $conditionNotes
                        : ($hasDamage ? $damageDescription : 'Kendaraan kembali dalam kondisi baik sesuai pemeriksaan akhir.'),
                    'condition_photo' => $storedPath,
                    'has_damage' => $hasDamage,
                    'damage_description' => $hasDamage ? $damageDescription : null,
                    'penalty_fee' => $penalty['late_fee_amount'],
                    'penalty_reason' => $penalty['reason'],
                    'inspected_at' => $actualReturnAt,
                ]);

                $previousStatus = $lockedRental->rental_status;
                $lateFee = $penalty['late_fee_amount'];
                $fuelChanged = $fuelLevel !== $pickupInspection->fuel_level;

                $noticeParts = ['Kendaraan telah dikembalikan pada '.$actualReturnAt->format('d M Y H:i').' WIB.'];
                if ($lateFee > 0) {
                    $noticeParts[] = 'Tercatat denda keterlambatan Rp '.number_format($lateFee, 0, ',', '.').'.';
                }
                if ($fuelChanged || $hasDamage) {
                    $noticeParts[] = 'Menunggu pemeriksaan admin untuk penetapan biaya BBM atau kerusakan sebelum transaksi ditutup.';
                } else {
                    $noticeParts[] = 'Menunggu pemeriksaan biaya tambahan akhir dan penyelesaian transaksi oleh admin.';
                }

                $lockedRental->forceFill([
                    'actual_return_at' => $actualReturnAt,
                    'late_minutes' => $penalty['late_minutes'],
                    'late_fee_amount' => $lateFee,
                    'additional_charges_total' => $lateFee,
                    'additional_charge_status' => $lateFee > 0 ? Rental::ADDITIONAL_CHARGE_UNPAID : Rental::ADDITIONAL_CHARGE_NONE,
                ]);
                $lockedRental->transitionRentalStatus(Rental::STATUS_RETURNED, implode(' ', $noticeParts));
                $lockedRental->saveOrFail();

                // Pisahkan tiga kondisi pascapengembalian (SKPL S-05 & Alur Bisnis 6):
                // rental berstatus 'returned', mobil ditahan pada needs_maintenance atau needs_preparation
                // sampai staf/admin menyatakan layak, dan transaksi belum selesai ('completed') sebelum kewajiban lunas.
                if ($hasDamage) {
                    $car->forceFill([
                        'status' => 'maintenance',
                        'maintenance_reason' => 'Kerusakan pascasewa '.$lockedRental->rental_code.': '.$damageDescription,
                        'readiness_status' => Car::READINESS_NEEDS_MAINTENANCE,
                        'readiness_notes' => 'Unit mengalami kerusakan pada pengembalian dan ditahan hingga perbaikan selesai.',
                        'readiness_checked_by' => $staff->id,
                        'readiness_checked_at' => $actualReturnAt,
                    ])->saveOrFail();
                } else {
                    $car->forceFill([
                        'status' => 'available',
                        'readiness_status' => Car::READINESS_NEEDS_PREPARATION,
                        'readiness_notes' => 'Menunggu pemeriksaan kebersihan dan kesiapan ulang setelah transaksi '.$lockedRental->rental_code.'.',
                        'readiness_checked_by' => $staff->id,
                        'readiness_checked_at' => $actualReturnAt,
                    ])->saveOrFail();
                }

                $lockedRental->recordHistory(
                    $staff,
                    'vehicle_returned',
                    $previousStatus,
                    Rental::STATUS_RETURNED,
                    $hasDamage ? $damageDescription : 'Pengembalian kendaraan dan inspeksi akhir dicatat.',
                    [
                        'inspection_id' => $inspection->id,
                        'odometer_km' => $odometerKm,
                        'fuel_level' => $fuelLevel,
                        'late_minutes' => $penalty['late_minutes'],
                        'late_fee_amount' => $lateFee,
                        'has_damage' => $hasDamage,
                        'actual_return_at' => $actualReturnAt->toIso8601String(),
                    ]
                );

                // Deteksi dan catat pesanan berikutnya yang terdampak oleh keterlambatan atau kerusakan unit (SYS-10).
                $this->flagImpactedNextBookingsInternal($car, $staff, $actualReturnAt);

                return $inspection;
            });
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            Storage::disk('local')->delete($storedPath);

            $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
            if ((string) $sqlState === '23505') {
                throw ValidationException::withMessages([
                    'return' => 'Inspeksi pengembalian untuk pesanan ini sudah pernah dicatat.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * Laporkan kerusakan unit oleh staf atau admin sehingga kendaraan ditahan pada status perawatan (S-05, A4).
     *
     * @throws ValidationException
     */
    public function reportVehicleDamage(Car $car, User $actor, string $reason): Car
    {
        if (! $actor->isStaff() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'reason' => 'Hanya staf operasional atau admin yang berhak melaporkan kerusakan kendaraan.',
            ]);
        }

        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan atau rincian kerusakan wajib diisi minimal 5 karakter.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($car, $actor, $trimmedReason, $nowWib): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->id);

            if ($lockedCar->isArchived()) {
                throw ValidationException::withMessages([
                    'reason' => 'Kendaraan yang sudah diarsipkan tidak dapat diubah status kesiapannya.',
                ]);
            }

            $lockedCar->forceFill([
                'status' => 'maintenance',
                'maintenance_reason' => $trimmedReason,
                'readiness_status' => Car::READINESS_NEEDS_MAINTENANCE,
                'readiness_notes' => $trimmedReason,
                'readiness_checked_by' => $actor->id,
                'readiness_checked_at' => $nowWib,
            ])->saveOrFail();

            $this->flagImpactedNextBookingsInternal($lockedCar, $actor, $nowWib);

            return $lockedCar->refresh();
        });
    }

    /**
     * Nyatakan kendaraan layak disewakan kembali oleh staf atau admin setelah pemeriksaan dan persiapan (S-05, A4).
     *
     * @throws ValidationException
     */
    public function markVehicleReady(Car $car, User $actor, ?string $notes = null): Car
    {
        if (! $actor->isStaff() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'readiness' => 'Hanya staf operasional atau admin yang berhak menyatakan unit siap disewakan kembali.',
            ]);
        }

        $trimmedNotes = trim((string) $notes);
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($car, $actor, $trimmedNotes, $nowWib): Car {
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->id);

            if ($lockedCar->isArchived()) {
                throw ValidationException::withMessages([
                    'readiness' => 'Kendaraan arsip tidak dapat ditandai siap disewa.',
                ]);
            }

            $hasActiveTrip = Rental::query()
                ->where('car_id', $lockedCar->id)
                ->where('rental_status', Rental::STATUS_PICKED_UP)
                ->lockForUpdate()
                ->exists();

            if ($hasActiveTrip) {
                throw ValidationException::withMessages([
                    'readiness' => 'Kendaraan masih berada di tangan penyewa dan belum dikembalikan.',
                ]);
            }

            $lockedCar->forceFill([
                'status' => 'available',
                'maintenance_reason' => null,
                'readiness_status' => Car::READINESS_READY,
                'readiness_notes' => $trimmedNotes !== ''
                    ? $trimmedNotes
                    : 'Unit telah diperiksa, dibersihkan, dan dinyatakan layak untuk serah-terima berikutnya.',
                'readiness_checked_by' => $actor->id,
                'readiness_checked_at' => $nowWib,
            ])->saveOrFail();

            return $lockedCar->refresh();
        });
    }

    /**
     * Deteksi daftar pesanan berikutnya yang terancam akibat keterlambatan pengembalian atau kerusakan kendaraan (SYS-10).
     *
     * @return Collection<int, array{rental: Rental, car: Car, reason: string, severity: string}>
     */
    public function detectImpactedNextBookings(?Car $car = null, ?CarbonInterface $at = null): Collection
    {
        $nowWib = $at
            ? CarbonImmutable::instance($at)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        $upcomingRentals = Rental::query()
            ->with(['user', 'car'])
            ->when($car !== null, fn ($q) => $q->where('car_id', $car->id))
            ->whereIn('rental_status', [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED])
            ->whereRaw(
                'NOT (rental_status = ? AND payment_status IN (?, ?) AND payment_deadline_at IS NOT NULL AND payment_deadline_at <= ?)',
                [Rental::STATUS_PENDING, Rental::PAYMENT_UNPAID, Rental::PAYMENT_REJECTED, $nowWib->format('Y-m-d H:i:s')]
            )
            ->orderBy('pickup_at')
            ->get();

        if ($upcomingRentals->isEmpty()) {
            return collect();
        }

        $carIds = $upcomingRentals->pluck('car_id')->unique()->values()->all();

        $activeTripsByCar = Rental::query()
            ->whereIn('car_id', $carIds)
            ->where('rental_status', Rental::STATUS_PICKED_UP)
            ->get()
            ->groupBy('car_id');

        $recentReturnsByCar = Rental::query()
            ->whereIn('car_id', $carIds)
            ->where('rental_status', Rental::STATUS_RETURNED)
            ->orderByDesc('actual_return_at')
            ->get()
            ->groupBy('car_id');

        $impacts = collect();

        foreach ($upcomingRentals as $upcoming) {
            $unit = $upcoming->car;
            if ($unit === null) {
                continue;
            }

            $pickupWib = CarbonImmutable::instance($upcoming->pickup_at)->timezone(RentalScheduleService::TIMEZONE);
            $requiredReadyAt = $pickupWib->subMinutes(RentalScheduleService::BUFFER_MINUTES);

            if ($unit->isInMaintenance() || $unit->readiness_status === Car::READINESS_NEEDS_MAINTENANCE) {
                $impacts->push([
                    'rental' => $upcoming,
                    'car' => $unit,
                    'reason' => 'Unit '.$unit->name.' sedang perawatan / mengalami kerusakan ('.$unit->maintenance_reason.').',
                    'severity' => 'high',
                ]);

                continue;
            }

            $activeTrips = $activeTripsByCar->get($unit->id, collect());
            $threateningTrip = $activeTrips->first(function (Rental $trip) use ($nowWib, $requiredReadyAt): bool {
                $returnDeadline = CarbonImmutable::instance($trip->return_at)->timezone(RentalScheduleService::TIMEZONE);
                $projectedAvailableAt = $nowWib->greaterThan($returnDeadline) ? $nowWib : $returnDeadline;

                return $projectedAvailableAt->greaterThan($requiredReadyAt);
            });

            if ($threateningTrip !== null) {
                $impacts->push([
                    'rental' => $upcoming,
                    'car' => $unit,
                    'reason' => 'Penyewaan aktif '.$threateningTrip->rental_code.' terlambat atau melampaui jeda persiapan 1 jam sebelum jadwal pengambilan '
                        .$pickupWib->format('d M Y H:i').' WIB.',
                    'severity' => 'high',
                ]);

                continue;
            }

            $latestReturn = $recentReturnsByCar->get($unit->id, collect())->first();
            if ($latestReturn !== null && $latestReturn->actual_return_at !== null) {
                $actualReturnWib = CarbonImmutable::instance($latestReturn->actual_return_at)->timezone(RentalScheduleService::TIMEZONE);
                if ($actualReturnWib->greaterThan($requiredReadyAt)) {
                    $impacts->push([
                        'rental' => $upcoming,
                        'car' => $unit,
                        'reason' => 'Pengembalian aktual transaksi '.$latestReturn->rental_code.' pada '
                            .$actualReturnWib->format('d M Y H:i').' WIB mengganggu jeda persiapan 1 jam sebelum jadwal ambil '
                            .$pickupWib->format('d M Y H:i').' WIB.',
                        'severity' => 'warning',
                    ]);

                    continue;
                }
            }

            if ($unit->readiness_status === Car::READINESS_NEEDS_PREPARATION && $nowWib->greaterThanOrEqualTo($requiredReadyAt)) {
                $impacts->push([
                    'rental' => $upcoming,
                    'car' => $unit,
                    'reason' => 'Unit '.$unit->name.' belum dinyatakan siap serah-terima padahal jadwal pengambilan kurang dari 1 jam lagi.',
                    'severity' => 'warning',
                ]);
            }
        }

        return $impacts;
    }

    /**
     * Deteksi serta catat peringatan pesanan berikutnya yang terdampak ke riwayat transaksi (SYS-10, SYS-13).
     *
     * @return Collection<int, array{rental: Rental, car: Car, reason: string, severity: string}>
     */
    private function flagImpactedNextBookingsInternal(Car $car, ?User $actor, CarbonInterface $at): Collection
    {
        $impacts = $this->detectImpactedNextBookings($car, $at);

        foreach ($impacts as $impact) {
            /** @var Rental $impactedRental */
            $impactedRental = $impact['rental'];
            $reason = $impact['reason'];

            $alreadyLogged = $impactedRental->histories()
                ->where('action', 'next_booking_impacted')
                ->where('reason', $reason)
                ->exists();

            if (! $alreadyLogged) {
                $impactedRental->forceFill([
                    'status_notice' => 'Peringatan jadwal terdampak: '.$reason.' Admin akan menawarkan kendaraan pengganti, penjadwalan ulang, atau pengembalian dana penuh.',
                ])->save();

                $impactedRental->recordHistory(
                    $actor,
                    'next_booking_impacted',
                    $impactedRental->rental_status,
                    $impactedRental->rental_status,
                    $reason,
                    [
                        'car_id' => $car->id,
                        'severity' => $impact['severity'],
                    ]
                );
            }
        }

        return $impacts;
    }
}
