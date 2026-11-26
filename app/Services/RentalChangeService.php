<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalChangeService
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Ajukan permohonan perubahan jadwal oleh pelanggan sebelum masa sewa dimulai (C-07).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function createCustomerRequest(Rental $rental, User $customer, array $payload): RentalChangeRequest
    {
        // Operator !== memeriksa identitas pemilik pesanan di level layanan agar pelanggan lain
        // tetap ditolak walaupun pemanggilan tidak melalui controller web.
        if ($rental->user_id !== $customer->id) {
            throw ValidationException::withMessages([
                'rental_id' => 'Anda tidak berhak mengajukan perubahan pada pesanan milik orang lain.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $pickupAt = $this->scheduleService->parsePickupAt((string) ($payload['proposed_pickup_at'] ?? ''));
        $totalDays = (int) ($payload['proposed_total_days'] ?? 0);
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($pickupAt->lessThan($nowWib)) {
            throw ValidationException::withMessages([
                'proposed_pickup_at' => 'Jadwal pengambilan baru tidak boleh berada di waktu lampau.',
            ]);
        }

        if ($totalDays < 1 || $totalDays > RentalScheduleService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'proposed_total_days' => 'Jumlah hari sewa usulan harus antara 1 sampai 365 hari.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan perubahan jadwal wajib diisi.',
            ]);
        }

        // DB::transaction() memastikan penguncian baris pesanan, pengecekan jadwal, dan penyimpanan
        // permohonan perubahan berjalan atomik tanpa kondisi balapan (NFR-04).
        return DB::transaction(function () use ($rental, $customer, $pickupAt, $totalDays, $reason, $nowWib): RentalChangeRequest {
            // Ikuti urutan kunci global: Car -> Rental.
            $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)
                || $lockedRental->isPaymentExpired($nowWib) || $lockedRental->isIdentityExpired($nowWib)) {
                throw ValidationException::withMessages([
                    'rental_id' => 'Perubahan jadwal hanya dapat diajukan sebelum kendaraan diambil.',
                ]);
            }

            if ($this->hasOpenRequest($lockedRental)) {
                throw ValidationException::withMessages([
                    'rental_id' => 'Masih ada usulan perubahan yang menunggu persetujuan pada pesanan ini.',
                ]);
            }

            $pricing = $this->scheduleService->calculatePricing($car, $pickupAt, $totalDays);

            // Perubahan jadwal mempertahankan tarif harian yang disepakati saat pemesanan (SKPL §Alur Bisnis 1).
            $proposedTotalAmount = round($totalDays * (float) $lockedRental->daily_price, 2);

            if ($lockedRental->pickup_at !== null
                && $lockedRental->pickup_at->copy()->timezone(RentalScheduleService::TIMEZONE)->equalTo($pricing['pickup_at'])
                && $lockedRental->total_days === $totalDays) {
                throw ValidationException::withMessages([
                    'proposed_pickup_at' => 'Jadwal baru yang diusulkan masih sama dengan jadwal saat ini.',
                ]);
            }

            $this->guardFinancialChangeOnProposal($lockedRental, $proposedTotalAmount, false);

            if (! $this->scheduleService->isCarAvailableForPeriod($car, $pricing['pickup_at'], $pricing['return_at'], $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'proposed_pickup_at' => 'Kendaraan tidak tersedia pada jadwal usulan tersebut atau bentrok dengan jeda persiapan 1 jam.',
                ]);
            }

            $changeRequest = RentalChangeRequest::create([
                'rental_id' => $lockedRental->id,
                'request_type' => RentalChangeRequest::TYPE_RESCHEDULE,
                'initiated_by_role' => 'customer',
                'requested_by' => $customer->id,
                'proposed_car_id' => $car->id,
                'proposed_pickup_at' => $pricing['pickup_at'],
                'proposed_total_days' => $totalDays,
                'proposed_return_at' => $pricing['return_at'],
                'proposed_total_amount' => $proposedTotalAmount,
                'waive_price_difference' => false,
                'reason' => $reason,
                'status' => RentalChangeRequest::STATUS_PENDING_ADMIN,
            ]);

            $lockedRental->forceFill([
                'status_notice' => 'Permohonan perubahan jadwal Anda sedang menunggu pemeriksaan dan persetujuan admin.',
            ])->save();

            $lockedRental->recordHistory(
                $customer,
                'change_requested',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $reason,
                [
                    'change_request_id' => $changeRequest->id,
                    'request_type' => $changeRequest->request_type,
                    'proposed_pickup_at' => $pricing['pickup_at']->toIso8601String(),
                    'proposed_total_days' => $totalDays,
                    'proposed_total_amount' => $proposedTotalAmount,
                ]
            );

            return $changeRequest;
        });
    }

    /**
     * Buat usulan perubahan jadwal atau penggantian kendaraan oleh admin yang menunggu persetujuan pelanggan (A-06).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function proposeByAdmin(Rental $rental, User $admin, array $payload): RentalChangeRequest
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'rental_id' => 'Hanya admin yang berhak membuat usulan penanganan pesanan.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $requestType = (string) ($payload['request_type'] ?? RentalChangeRequest::TYPE_RESCHEDULE);
        $reason = trim((string) ($payload['reason'] ?? ''));
        $waiveDifference = (bool) ($payload['waive_price_difference'] ?? false);

        if (! in_array($requestType, [RentalChangeRequest::TYPE_RESCHEDULE, RentalChangeRequest::TYPE_CAR_REPLACEMENT], true)) {
            throw ValidationException::withMessages([
                'request_type' => 'Jenis usulan perubahan pesanan tidak dikenali.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan usulan perubahan atau penggantian unit wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($rental, $admin, $payload, $requestType, $reason, $waiveDifference, $nowWib): RentalChangeRequest {
            $targetCarId = $requestType === RentalChangeRequest::TYPE_CAR_REPLACEMENT
                ? (int) ($payload['proposed_car_id'] ?? 0)
                : (int) $rental->car_id;

            if ($targetCarId <= 0) {
                throw ValidationException::withMessages([
                    'proposed_car_id' => 'Kendaraan pengganti harus dipilih.',
                ]);
            }

            // Kunci seluruh mobil terkait berurutan naik sebelum mengunci baris Rental agar terhindar dari deadlock.
            $carIdsToLock = array_values(array_unique([$rental->car_id, $targetCarId]));
            sort($carIdsToLock);
            Car::query()->whereIn('id', $carIdsToLock)->orderBy('id')->lockForUpdate()->get();

            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)
                || $lockedRental->isPaymentExpired($nowWib) || $lockedRental->isIdentityExpired($nowWib)) {
                throw ValidationException::withMessages([
                    'rental_id' => 'Usulan perubahan atau penggantian unit hanya berlaku pada pesanan berstatus menunggu atau terkonfirmasi.',
                ]);
            }

            if ($this->hasOpenRequest($lockedRental)) {
                throw ValidationException::withMessages([
                    'rental_id' => 'Masih ada usulan perubahan yang belum ditanggapi pada pesanan ini.',
                ]);
            }

            $targetCar = Car::query()->find($targetCarId);
            if (! $targetCar instanceof Car || ! $targetCar->isBookableForSchedule()) {
                throw ValidationException::withMessages([
                    'proposed_car_id' => 'Kendaraan pengganti tidak ditemukan atau sedang tidak tersedia untuk disewa.',
                ]);
            }

            $pickupInput = trim((string) ($payload['proposed_pickup_at'] ?? ''));
            $pickupAt = $pickupInput !== ''
                ? $this->scheduleService->parsePickupAt($pickupInput)
                : CarbonImmutable::instance($lockedRental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);

            if ($pickupInput !== '' && $pickupAt->lessThan($nowWib)) {
                throw ValidationException::withMessages([
                    'proposed_pickup_at' => 'Jadwal pengambilan usulan tidak boleh berada di waktu lampau.',
                ]);
            }

            $totalDays = isset($payload['proposed_total_days']) && $payload['proposed_total_days'] !== ''
                ? (int) $payload['proposed_total_days']
                : (int) $lockedRental->total_days;

            if ($totalDays < 1 || $totalDays > RentalScheduleService::MAX_DAYS) {
                throw ValidationException::withMessages([
                    'proposed_total_days' => 'Jumlah hari sewa usulan harus antara 1 sampai 365 hari.',
                ]);
            }

            $sameCar = $targetCar->id === $lockedRental->car_id;
            $samePickup = $lockedRental->pickup_at !== null
                && $lockedRental->pickup_at->copy()->timezone(RentalScheduleService::TIMEZONE)->equalTo($pickupAt);
            $sameDays = $lockedRental->total_days === $totalDays;

            if ($sameCar && $samePickup && $sameDays) {
                throw ValidationException::withMessages([
                    'reason' => 'Usulan tidak mengubah unit mobil maupun jadwal penyewaan.',
                ]);
            }

            $pricing = $this->scheduleService->calculatePricing($targetCar, $pickupAt, $totalDays);

            // Jika admin membebaskan selisih biaya karena kendala operasional pihak rental (A-06),
            // total tagihan usulan dipertahankan pada nilai pesanan awal sehingga biaya tambahan = 0.
            // Untuk penjadwalan ulang pada unit yang sama, tarif harian saat pemesanan tetap dipertahankan.
            $calculatedTotal = $requestType === RentalChangeRequest::TYPE_RESCHEDULE
                ? round($totalDays * (float) $lockedRental->daily_price, 2)
                : (float) $pricing['total_amount'];

            $proposedTotalAmount = $waiveDifference
                ? (float) $lockedRental->total_amount
                : $calculatedTotal;

            $this->guardFinancialChangeOnProposal($lockedRental, $proposedTotalAmount, $waiveDifference);

            if (! $this->scheduleService->isCarAvailableForPeriod($targetCar, $pricing['pickup_at'], $pricing['return_at'], $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'proposed_car_id' => 'Kendaraan atau jadwal usulan tidak tersedia atau bentrok dengan jeda persiapan 1 jam.',
                ]);
            }

            $changeRequest = RentalChangeRequest::create([
                'rental_id' => $lockedRental->id,
                'request_type' => $requestType,
                'initiated_by_role' => 'admin',
                'requested_by' => $admin->id,
                'proposed_car_id' => $targetCar->id,
                'proposed_pickup_at' => $pricing['pickup_at'],
                'proposed_total_days' => $totalDays,
                'proposed_return_at' => $pricing['return_at'],
                'proposed_total_amount' => $proposedTotalAmount,
                'waive_price_difference' => $waiveDifference,
                'reason' => $reason,
                'status' => RentalChangeRequest::STATUS_PENDING_CUSTOMER,
            ]);

            $lockedRental->forceFill([
                'status_notice' => 'Admin mengusulkan penyesuaian pesanan. Mohon tinjau dan setujui atau tolak usulan pada detail pesanan.',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'change_proposed_by_admin',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $reason,
                [
                    'change_request_id' => $changeRequest->id,
                    'request_type' => $requestType,
                    'proposed_car_id' => $targetCar->id,
                    'proposed_pickup_at' => $pricing['pickup_at']->toIso8601String(),
                    'proposed_total_days' => $totalDays,
                    'proposed_total_amount' => $proposedTotalAmount,
                    'waive_price_difference' => $waiveDifference,
                ]
            );

            return $changeRequest;
        });
    }

    /**
     * Proses persetujuan atau penolakan atas usulan perubahan oleh pihak yang berwenang (A-06, C-07).
     *
     * @throws ValidationException
     */
    public function respond(RentalChangeRequest $changeRequest, User $actor, string $decision, ?string $notes = null, ?WalkInConfirmation $confirmation = null): RentalChangeRequest
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan usulan perubahan harus berupa persetujuan atau penolakan.',
            ]);
        }

        $trimmedNotes = trim((string) $notes);
        if ($decision === 'reject' && $trimmedNotes === '') {
            throw ValidationException::withMessages([
                'response_notes' => 'Alasan penolakan usulan perubahan wajib diisi.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($changeRequest, $actor, $decision, $trimmedNotes, $nowWib, $confirmation): RentalChangeRequest {
            // Muat car_id awal terlebih dahulu lalu kunci Car (urutan ID menaik) -> Rental -> RentalChangeRequest
            // agar urutan penguncian database konsisten di seluruh layanan transaksi (bebas deadlock).
            $initialRental = Rental::query()->findOrFail($changeRequest->rental_id);
            $carIdsToLock = array_values(array_unique(array_filter([
                $initialRental->car_id,
                $changeRequest->proposed_car_id,
            ])));
            sort($carIdsToLock);
            if ($carIdsToLock !== []) {
                Car::query()->whereIn('id', $carIdsToLock)->orderBy('id')->lockForUpdate()->get();
            }

            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($changeRequest->rental_id);
            $lockedRequest = RentalChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);

            if (! in_array($lockedRental->car_id, $carIdsToLock, true)) {
                throw ValidationException::withMessages(['decision' => 'Unit pesanan berubah. Muat ulang sebelum mencatat keputusan.']);
            }

            // Persetujuan manual hanya berlaku untuk usulan admin pada walk-in.
            // Pelaku histori tetap admin yang mencatat, bukan akun penyewa.
            if ($confirmation !== null && (! $actor->isAdmin()
                || $lockedRental->booking_channel !== Rental::CHANNEL_WALK_IN
                || $lockedRequest->status !== RentalChangeRequest::STATUS_PENDING_CUSTOMER)) {
                throw ValidationException::withMessages(['decision' => 'Konfirmasi manual hanya tersedia untuk usulan walk-in yang masih menunggu jawaban.']);
            }
            if ($confirmation !== null && $confirmation->occurredAt()->lt($lockedRequest->created_at)) {
                throw ValidationException::withMessages(['confirmed_at' => 'Waktu jawaban tidak boleh mendahului usulan.']);
            }

            if ($lockedRequest->status === RentalChangeRequest::STATUS_PENDING_ADMIN) {
                if (! $actor->isAdmin()) {
                    throw ValidationException::withMessages([
                        'decision' => 'Hanya admin yang dapat menanggapi permohonan perubahan pelanggan.',
                    ]);
                }
            } elseif ($lockedRequest->status === RentalChangeRequest::STATUS_PENDING_CUSTOMER) {
                if ($lockedRental->user_id !== $actor->id && $confirmation === null) {
                    throw ValidationException::withMessages([
                        'decision' => 'Hanya pelanggan pemilik pesanan yang dapat menyetujui atau menolak usulan admin.',
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'decision' => 'Usulan perubahan ini sudah ditanggapi sebelumnya.',
                ]);
            }

            if ($decision === 'reject') {
                $lockedRequest->forceFill([
                    'status' => RentalChangeRequest::STATUS_REJECTED,
                    'responded_by' => $actor->id,
                    'responded_at' => $nowWib,
                    'response_notes' => $trimmedNotes,
                ])->save();

                $lockedRental->forceFill([
                    'status_notice' => 'Usulan perubahan pesanan ditolak; jadwal dan kendaraan sebelumnya tetap berlaku.',
                ])->save();

                $lockedRental->recordHistory(
                    $actor,
                    'change_rejected',
                    $lockedRental->rental_status,
                    $lockedRental->rental_status,
                    $trimmedNotes,
                    [
                        'change_request_id' => $lockedRequest->id,
                        'request_type' => $lockedRequest->request_type,
                    ] + ($confirmation?->metadata() ?? [])
                );

                return $lockedRequest->refresh();
            }

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)
                || $lockedRental->isPaymentExpired($nowWib) || $lockedRental->isIdentityExpired($nowWib)) {
                throw ValidationException::withMessages([
                    'decision' => 'Pesanan ini tidak lagi berada pada status yang memungkinkan perubahan jadwal atau unit.',
                ]);
            }

            $targetCarId = (int) ($lockedRequest->proposed_car_id ?: $lockedRental->car_id);
            if (! in_array($targetCarId, $carIdsToLock, true)) {
                throw ValidationException::withMessages(['decision' => 'Unit usulan berubah. Muat ulang sebelum mencatat keputusan.']);
            }

            $targetCar = Car::query()->find($targetCarId);
            if (! $targetCar instanceof Car || ! $targetCar->isBookableForSchedule()) {
                throw ValidationException::withMessages([
                    'decision' => 'Kendaraan tujuan tidak tersedia untuk perubahan ini.',
                ]);
            }

            $proposedPickupAt = CarbonImmutable::instance($lockedRequest->proposed_pickup_at ?? $lockedRental->pickup_at)
                ->timezone(RentalScheduleService::TIMEZONE);
            $proposedTotalDays = (int) ($lockedRequest->proposed_total_days ?? $lockedRental->total_days);
            $proposedReturnAt = CarbonImmutable::instance($lockedRequest->proposed_return_at ?? $lockedRental->return_at)
                ->timezone(RentalScheduleService::TIMEZONE);

            if ($proposedPickupAt->lte($nowWib)) {
                throw ValidationException::withMessages(['decision' => 'Jadwal ambil baru sudah terlewati. Buat usulan baru.']);
            }

            // Pemeriksaan ulang ketersediaan + jeda 1 jam dilakukan tepat saat persetujuan
            // agar perubahan yang sempat mengantre tidak bentrok dengan pesanan lain yang masuk belakangan.
            if (! $this->scheduleService->isCarAvailableForPeriod($targetCar, $proposedPickupAt, $proposedReturnAt, $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'decision' => 'Persetujuan gagal karena jadwal atau kendaraan pengganti kini bentrok dengan pesanan lain atau masa jeda 1 jam.',
                ]);
            }

            $previousSnapshot = [
                'car_id' => $lockedRental->car_id,
                'pickup_at' => $lockedRental->pickup_at?->toIso8601String(),
                'return_at' => $lockedRental->return_at?->toIso8601String(),
                'total_days' => $lockedRental->total_days,
                'total_amount' => (float) $lockedRental->total_amount,
            ];

            $newDailyPrice = ($lockedRequest->waive_price_difference || $lockedRequest->request_type === RentalChangeRequest::TYPE_RESCHEDULE)
                ? (float) $lockedRental->daily_price
                : (float) $targetCar->price_per_day;

            $newDeadline = $lockedRental->payment_deadline_at;
            if ($lockedRental->rental_status === Rental::STATUS_PENDING && $newDeadline !== null) {
                // Jika jadwal pengambilan dimajukan sehingga lebih dekat daripada tenggat pembayaran saat ini,
                // tenggat diperketat agar tidak melewati waktu pengambilan, tetapi tidak pernah diperpanjang otomatis.
                $tightened = $this->scheduleService->calculatePaymentDeadline($nowWib, $proposedPickupAt);
                if ($tightened->lessThan($newDeadline->copy()->timezone(RentalScheduleService::TIMEZONE))) {
                    $newDeadline = $tightened;
                }
            }

            $previousIdentityDeadline = $lockedRental->identity_deadline_at;
            // Jadwal baru yang disetujui membuka kesempatan perbaikan hanya
            // jika pemeriksaan admin menyebabkan kesempatan awal hilang. Tenggat
            // penolakan biasa tidak diperpanjang oleh usulan atau unggah ulang.
            if ($lockedRental->review_followup_at !== null && $proposedPickupAt->ne($lockedRental->pickup_at)) {
                $lockedRental->review_followup_at = null;
                if ($lockedRental->identity_status === Rental::IDENTITY_FOLLOWUP
                    || ($lockedRental->identity_status === Rental::IDENTITY_PENDING
                        && $previousIdentityDeadline?->lte($nowWib))) {
                    $lockedRental->identity_deadline_at = $nowWib->addHours(6)->min($proposedPickupAt);
                    if ($lockedRental->identity_status === Rental::IDENTITY_FOLLOWUP) {
                        $lockedRental->identity_status = Rental::IDENTITY_REJECTED;
                    }
                }
                if ($lockedRental->payment_review_blocked) {
                    $newDeadline = $nowWib->addHour()->min($proposedPickupAt);
                    $lockedRental->payment_review_blocked = false;
                }
            }
            if ($lockedRental->identity_deadline_at?->gt($proposedPickupAt)) {
                $lockedRental->identity_deadline_at = $proposedPickupAt;
            }

            $lockedRental->forceFill([
                'car_id' => $targetCar->id,
                'pickup_at' => $proposedPickupAt,
                'return_at' => $proposedReturnAt,
                'start_date' => $proposedPickupAt->toDateString(),
                'end_date' => $proposedReturnAt->toDateString(),
                'total_days' => $proposedTotalDays,
                'daily_price' => $newDailyPrice,
                'total_amount' => (float) $lockedRequest->proposed_total_amount,
                'payment_deadline_at' => $newDeadline,
                'status_notice' => 'Perubahan pesanan telah disetujui dan jadwal atau unit terbaru kini aktif.',
            ]);
            $lockedRental->confirmWhenReady($nowWib);
            $lockedRental->saveOrFail();

            $lockedRequest->forceFill([
                'status' => RentalChangeRequest::STATUS_APPROVED,
                'responded_by' => $actor->id,
                'responded_at' => $nowWib,
                'response_notes' => $trimmedNotes !== '' ? $trimmedNotes : 'Disetujui.',
            ])->save();

            $lockedRental->recordHistory(
                $actor,
                'change_approved',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedNotes !== '' ? $trimmedNotes : $lockedRequest->reason,
                [
                    'change_request_id' => $lockedRequest->id,
                    'request_type' => $lockedRequest->request_type,
                    'waive_price_difference' => $lockedRequest->waive_price_difference,
                    'previous_identity_deadline_at' => $previousIdentityDeadline?->toIso8601String(),
                    'identity_deadline_at' => $lockedRental->identity_deadline_at?->toIso8601String(),
                    'before' => $previousSnapshot,
                    'after' => [
                        'car_id' => $targetCar->id,
                        'pickup_at' => $proposedPickupAt->toIso8601String(),
                        'return_at' => $proposedReturnAt->toIso8601String(),
                        'total_days' => $proposedTotalDays,
                        'total_amount' => (float) $lockedRequest->proposed_total_amount,
                    ],
                ] + ($confirmation?->metadata() ?? [])
            );

            return $lockedRequest->refresh();
        });
    }

    /**
     * Periksa apakah pesanan masih memiliki usulan perubahan yang belum selesai diputuskan.
     */
    private function hasOpenRequest(Rental $rental): bool
    {
        return RentalChangeRequest::query()
            ->where('rental_id', $rental->id)
            ->whereIn('status', [
                RentalChangeRequest::STATUS_PENDING_ADMIN,
                RentalChangeRequest::STATUS_PENDING_CUSTOMER,
            ])
            ->exists();
    }

    /**
     * Jaga konsistensi pembayaran agar usulan perubahan pada pesanan yang sudah lunas/antre tidak menaikkan tagihan sepihak.
     *
     * @throws ValidationException
     */
    private function guardFinancialChangeOnProposal(Rental $rental, float $proposedTotalAmount, bool $waiveDifference): void
    {
        if ($rental->payment_status === Rental::PAYMENT_PAID && ! $waiveDifference && $proposedTotalAmount > (float) $rental->paid_amount) {
            throw ValidationException::withMessages([
                'proposed_total_days' => 'Pesanan ini sudah lunas. Untuk menambah hari berbayar gunakan fitur Perpanjangan Penyewaan, atau centang pembebasan selisih biaya oleh admin.',
            ]);
        }

        if ($rental->payment_status === Rental::PAYMENT_PENDING_APPROVAL && round($proposedTotalAmount, 2) !== round((float) $rental->total_amount, 2)) {
            throw ValidationException::withMessages([
                'proposed_total_days' => 'Nominal tagihan tidak dapat diubah saat bukti pembayaran sedang menunggu verifikasi admin.',
            ]);
        }
    }
}
