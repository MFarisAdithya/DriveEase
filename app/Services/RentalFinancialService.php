<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalCashFlow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalFinancialService
{
    /**
     * Hitung persentase dan nominal hak refund pelanggan secara ketat sesuai kebijakan pembatalan SKPL (SYS-11).
     *
     * @return array{percentage: int, amount: float, paid_amount: float, reason_label: string}
     */
    public function calculateRefundPolicy(
        Rental $rental,
        string $cancellationType,
        ?CarbonInterface $cancelledAt = null
    ): array {
        $paidAmount = max(0.0, round((float) $rental->paid_amount, 2));
        $totalAmount = max(0.0, round((float) $rental->total_amount, 2));
        $alreadyRealized = max(0.0, round((float) $rental->refund_realized_amount, 2));
        $reference = $cancelledAt
            ? CarbonImmutable::instance($cancelledAt)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $pickupWib = CarbonImmutable::instance($rental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);

        if (in_array($cancellationType, [Rental::CANCEL_RENTAL_FAULT, Rental::CANCEL_IDENTITY, Rental::CANCEL_PAYMENT_EXPIRED], true)) {
            $percentage = 100;
            $label = 'Pembatalan karena kendala pihak rental (refund 100%)';
        } elseif ($cancellationType === Rental::CANCEL_NO_SHOW) {
            $percentage = 0;
            $label = 'Pelanggan tidak hadir pada jadwal pengambilan (refund 0%)';
        } else {
            // Gunakan selisih menit presisi agar batas 48 jam, 24 jam, dan 6 jam
            // sebelum jadwal pengambilan dihitung tepat sesuai kebijakan SKPL.
            $minutesBeforePickup = $reference->diffInMinutes($pickupWib, false);

            if ($minutesBeforePickup >= 48 * 60) {
                $percentage = 100;
                $label = 'Pembatalan >= 48 jam sebelum pengambilan (refund 100%)';
            } elseif ($minutesBeforePickup >= 24 * 60) {
                $percentage = 75;
                $label = 'Pembatalan 24 sampai < 48 jam sebelum pengambilan (refund 75%)';
            } elseif ($minutesBeforePickup >= 6 * 60) {
                $percentage = 50;
                $label = 'Pembatalan 6 sampai < 24 jam sebelum pengambilan (refund 50%)';
            } else {
                $percentage = 0;
                $label = 'Pembatalan < 6 jam sebelum pengambilan (refund 0%)';
            }
        }

        // Hak pembatalan dihitung atas dana tagihan utama (maks total_amount); apabila terdapat kelebihan bayar
        // yang belum dikembalikan, dana lebih tersebut selalu dikembalikan 100% tanpa terpotong penalti batal.
        $basePaid = min($paidAmount, $totalAmount > 0 ? $totalAmount : $paidAmount);
        $baseRefund = round($basePaid * ($percentage / 100), 2);
        $excessPaid = max(0.0, round($paidAmount - $basePaid, 2));
        $remainingUnrealizedExcess = max(0.0, round($excessPaid - $alreadyRealized, 2));
        $cashHeld = max(0.0, round($paidAmount - $alreadyRealized, 2));
        $amount = min($cashHeld, round($baseRefund + $remainingUnrealizedExcess, 2));

        return [
            'percentage' => $percentage,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'reason_label' => $label,
        ];
    }

    /**
     * Hitung ulang kewajiban setelah pembatalan atau rekonsiliasi. Refund yang
     * sudah ditransfer tetap tercatat, sehingga uang yang sama tidak ditagihkan ulang.
     */
    public function refreshCancellationRefund(Rental $rental): void
    {
        $type = $rental->cancellation_type ?? ($rental->rental_status === Rental::STATUS_EXPIRED
            ? Rental::CANCEL_PAYMENT_EXPIRED : Rental::CANCEL_CUSTOMER);
        $reference = $type === Rental::CANCEL_CUSTOMER ? $rental->cancellation_requested_at : null;
        $policy = $this->calculateRefundPolicy($rental, $type, $reference ?? $rental->cancelled_at);
        $realized = (float) $rental->refund_realized_amount;
        $rental->refund_percentage = $policy['percentage'];
        $rental->refund_amount = round($realized + $policy['amount'], 2);
        $rental->refund_status = $policy['amount'] > 0 ? Rental::REFUND_PENDING
            : ($realized > 0 ? Rental::REFUND_PROCESSED : Rental::REFUND_NONE);
    }

    /**
     * Hitung denda keterlambatan pengembalian sesuai kebijakan SKPL dan keputusan toleransi 30 menit awal (SYS-10, A3).
     *
     * @return array{late_minutes: int, billable_hours: int, full_days: int, late_fee_amount: float, reason: ?string}
     */
    public function calculateLatePenalty(Rental $rental, CarbonInterface $actualReturnAt): array
    {
        $agreedReturnWib = CarbonImmutable::instance($rental->return_at)->timezone(RentalScheduleService::TIMEZONE);
        $actualReturnWib = CarbonImmutable::instance($actualReturnAt)->timezone(RentalScheduleService::TIMEZONE);
        $dailyPrice = max(0.0, round((float) $rental->daily_price, 2));

        // Hitung keterlambatan aktual dalam detik agar pembulatan jam ke atas tepat walau lewat 1 detik.
        $lateSeconds = $agreedReturnWib->diffInSeconds($actualReturnWib, false);
        if ($lateSeconds <= 30 * 60) {
            return [
                'late_minutes' => max(0, (int) intdiv(max(0, $lateSeconds), 60)),
                'billable_hours' => 0,
                'full_days' => 0,
                'late_fee_amount' => 0.0,
                'reason' => null,
            ];
        }

        $lateMinutes = (int) ceil($lateSeconds / 60);
        $secondsPerDay = RentalScheduleService::HOURS_PER_DAY * 3600;
        $fullDays = (int) intdiv($lateSeconds, $secondsPerDay);
        $remainingSeconds = $lateSeconds % $secondsPerDay;

        $billableHours = 0;
        $totalFee = $fullDays * $dailyPrice;

        // Untuk > 24 jam, toleransi 30 menit hanya berlaku satu kali di awal:
        // sisa detik di atas kelipatan 24 jam langsung dihitung 10%/jam (ceil), dan > 5 jam = 1 hari penuh.
        if ($remainingSeconds > 0) {
            $remainingHoursCeil = (int) ceil($remainingSeconds / 3600);
            if ($remainingSeconds > 5 * 3600) {
                $fullDays++;
                $totalFee += $dailyPrice;
            } else {
                $billableHours = $remainingHoursCeil;
                $totalFee += round($remainingHoursCeil * 0.10 * $dailyPrice, 2);
            }
        }

        $totalFee = round($totalFee, 2);

        return [
            'late_minutes' => $lateMinutes,
            'billable_hours' => $billableHours,
            'full_days' => $fullDays,
            'late_fee_amount' => $totalFee,
            'reason' => 'Terlambat '.$lateMinutes.' menit dari batas kembali '.$agreedReturnWib->format('d M Y H:i').' WIB'
                ." ({$fullDays} hari penuh, {$billableHours} jam x 10% tarif harian).",
        ];
    }

    /**
     * Hitung total biaya tambahan gabungan dari denda telat, kekurangan BBM, dan kerusakan (SYS-11).
     *
     * @throws ValidationException
     */
    public function calculateAdditionalChargesTotal(float $lateFee, float $fuelFee, float $damageFee): float
    {
        if ($lateFee < 0 || $fuelFee < 0 || $damageFee < 0) {
            throw ValidationException::withMessages([
                'additional_charges' => 'Nominal denda, biaya BBM, atau kerusakan tidak boleh negatif.',
            ]);
        }

        return round($lateFee + $fuelFee + $damageFee, 2);
    }

    /**
     * Catat pengajuan pembatalan pesanan oleh pelanggan setelah bukti transfer dikirim atau lunas (C-08).
     *
     * @throws ValidationException
     */
    public function requestCustomerCancellation(Rental $rental, User $customer, string $reason): Rental
    {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'cancellation_request_reason' => 'Alasan pengajuan pembatalan wajib diisi minimal 5 karakter.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($rental, $customer, $trimmedReason, $nowWib): Rental {
            // Ikuti urutan kunci global: Car -> Rental.
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Pengajuan pembatalan hanya dapat dilakukan sebelum kendaraan diambil.',
                ]);
            }

            // Permintaan langsung tidak boleh mengganti alasan yang sudah
            // ditentukan oleh tenggat identitas atau pembayaran.
            if ($lockedRental->isPaymentExpired($nowWib) || $lockedRental->isIdentityExpired($nowWib)) {
                throw ValidationException::withMessages(['rental_status' => 'Tenggat telah terlewati. Buka ulang detail untuk melihat status terbaru.']);
            }

            if ($lockedRental->canBeCancelledUnpaid() && (float) $lockedRental->paid_amount <= 0) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Pesanan belum dibayar dapat dibatalkan langsung tanpa persetujuan admin.',
                ]);
            }

            if ($lockedRental->cancellation_requested_at !== null) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Pengajuan pembatalan untuk pesanan ini sudah dikirim dan sedang ditinjau admin.',
                ]);
            }

            $policy = $this->calculateRefundPolicy($lockedRental, Rental::CANCEL_CUSTOMER, $nowWib);

            $lockedRental->cancellation_requested_at = $nowWib;
            $lockedRental->cancellation_request_reason = $trimmedReason;
            $lockedRental->refund_percentage = $policy['percentage'];
            $lockedRental->refund_amount = $policy['amount'];
            $lockedRental->status_notice = 'Pengajuan pembatalan dikirim. Estimasi pengembalian dana sesuai kebijakan: '
                .$policy['percentage'].'% (Rp '.number_format($policy['amount'], 0, ',', '.').'). Menunggu konfirmasi admin.';
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $customer,
                'cancellation_requested',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                $trimmedReason,
                [
                    'estimated_refund_percentage' => $policy['percentage'],
                    'estimated_refund_amount' => $policy['amount'],
                ]
            );

            return $lockedRental;
        });
    }

    /**
     * Batalkan pesanan oleh admin dan tetapkan hak refund persis sesuai kebijakan SKPL tanpa penyimpangan (A-06, A-10, SYS-11).
     *
     * @throws ValidationException
     */
    public function cancelWithRefund(
        Rental $rental,
        User $admin,
        string $cancellationType,
        string $reason,
        bool $contactAttempted = false,
        ?WalkInConfirmation $confirmation = null,
    ): Rental {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['cancellation_type' => 'Hanya admin yang dapat menetapkan pembatalan ini.']);
        }

        if (! in_array($cancellationType, [Rental::CANCEL_CUSTOMER, Rental::CANCEL_RENTAL_FAULT, Rental::CANCEL_NO_SHOW], true)) {
            throw ValidationException::withMessages([
                'cancellation_type' => 'Jenis pembatalan tidak valid.',
            ]);
        }

        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Alasan pembatalan wajib diisi minimal 5 karakter.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($rental, $admin, $cancellationType, $trimmedReason, $contactAttempted, $nowWib, $confirmation): Rental {
            // Ikuti urutan kunci global: Car -> Rental.
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Hanya pesanan berstatus menunggu atau terkonfirmasi yang dapat dibatalkan.',
                ]);
            }

            // Permintaan langsung tidak boleh mengganti alasan yang sudah
            // ditentukan oleh tenggat identitas atau pembayaran.
            if ($lockedRental->isPaymentExpired($nowWib) || $lockedRental->isIdentityExpired($nowWib)) {
                throw ValidationException::withMessages(['rental_status' => 'Tenggat telah terlewati. Buka ulang detail untuk melihat status terbaru.']);
            }

            $requestedAt = $lockedRental->cancellation_requested_at;
            if ($cancellationType === Rental::CANCEL_CUSTOMER
                && $lockedRental->booking_channel === Rental::CHANNEL_WALK_IN && $requestedAt === null) {
                if ($confirmation === null) {
                    throw ValidationException::withMessages(['confirmation_method' => 'Catat konfirmasi permintaan pembatalan penyewa walk-in.']);
                }
                if ($confirmation->occurredAt()->lt($lockedRental->created_at->copy()->startOfMinute())) {
                    throw ValidationException::withMessages(['confirmed_at' => 'Permintaan tidak boleh mendahului pemesanan.']);
                }
                $requestedAt = $confirmation->occurredAt();
            }

            if ($lockedRental->review_followup_at !== null && $cancellationType === Rental::CANCEL_CUSTOMER
                && ($requestedAt === null || $requestedAt->gte($lockedRental->review_followup_at))) {
                throw ValidationException::withMessages(['cancellation_type' => 'Gunakan kendala pihak rental jika pembatalan disebabkan pemeriksaan yang terlambat.']);
            }

            if ($cancellationType === Rental::CANCEL_NO_SHOW) {
                $pickupWib = CarbonImmutable::instance($lockedRental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);

                // SKPL mensyaratkan pelanggan sudah melewati +1 jam dari jadwal ambil pada pesanan terkonfirmasi,
                // belum melakukan pengambilan, dan admin sudah mencoba menghubungi pelanggan lebih dahulu.
                if ($lockedRental->rental_status !== Rental::STATUS_CONFIRMED || $lockedRental->actual_pickup_at !== null
                    || $lockedRental->review_followup_at !== null || ! $lockedRental->hasVerifiedIdentity()) {
                    throw ValidationException::withMessages([
                        'cancellation_type' => 'Status tidak hadir (no-show) hanya berlaku pada pesanan terkonfirmasi yang belum diambil.',
                    ]);
                }

                if ($nowWib->lessThan($pickupWib->addHour())) {
                    throw ValidationException::withMessages([
                        'cancellation_type' => 'Pembatalan tidak hadir (no-show) baru dapat ditetapkan minimal 1 jam setelah jadwal pengambilan ('.$pickupWib->addHour()->format('d M Y H:i').' WIB).',
                    ]);
                }

                if (! $contactAttempted) {
                    throw ValidationException::withMessages([
                        'contact_attempted' => 'Admin wajib menghubungi pelanggan terlebih dahulu sebelum menetapkan pembatalan tidak hadir (no-show).',
                    ]);
                }
            }

            // Refund walk-in memakai waktu penyewa meminta pembatalan, sama
            // seperti kanal online. Simpan tanggalnya agar rekonsiliasi bukti
            // yang datang belakangan memakai kebijakan dan waktu yang sama.
            $policyReference = $cancellationType === Rental::CANCEL_CUSTOMER ? ($requestedAt ?? $nowWib) : $nowWib;

            // Hak pengembalian dana selalu ditetapkan tepat sesuai rumus kebijakan SKPL tanpa override sepihak,
            // lalu digabungkan dengan nominal refund yang sebelumnya sudah terealisasi (mis. kelebihan bayar awal).
            $alreadyRealized = max(0.0, round((float) $lockedRental->refund_realized_amount, 2));
            $policy = $this->calculateRefundPolicy($lockedRental, $cancellationType, $policyReference);
            $newObligation = $policy['amount'];
            $totalRefundEntitlement = round($alreadyRealized + $newObligation, 2);

            $previousStatus = $lockedRental->rental_status;
            $refundStatus = $newObligation > 0
                ? Rental::REFUND_PENDING
                : ($alreadyRealized > 0 ? Rental::REFUND_PROCESSED : Rental::REFUND_NONE);

            $lockedRental->transitionRentalStatus(
                Rental::STATUS_CANCELLED,
                $newObligation > 0
                    ? 'Pesanan dibatalkan. Hak pengembalian dana sebesar Rp '.number_format($newObligation, 0, ',', '.').' sedang diproses admin.'
                    : 'Pesanan dibatalkan tanpa sisa kewajiban pengembalian dana baru sesuai ketentuan.'
            );
            $lockedRental->cancelled_at = $nowWib;
            $lockedRental->cancellation_type = $cancellationType;
            if ($cancellationType === Rental::CANCEL_CUSTOMER && $lockedRental->cancellation_requested_at === null && $requestedAt !== null) {
                $lockedRental->cancellation_requested_at = $requestedAt;
                $lockedRental->cancellation_request_reason = $trimmedReason;
            }
            $lockedRental->cancellation_reason = $trimmedReason;
            $lockedRental->refund_percentage = $policy['percentage'];
            $lockedRental->refund_amount = $totalRefundEntitlement;
            $lockedRental->refund_status = $refundStatus;
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'booking_cancelled_with_refund',
                $previousStatus,
                Rental::STATUS_CANCELLED,
                $trimmedReason,
                [
                    'cancellation_type' => $cancellationType,
                    'contact_attempted' => $cancellationType === Rental::CANCEL_NO_SHOW ? true : null,
                    'refund_percentage' => $policy['percentage'],
                    'new_refund_obligation' => $newObligation,
                    'refund_amount' => $totalRefundEntitlement,
                    'refund_status' => $refundStatus,
                ] + ($confirmation?->metadata() ?? [])
            );

            return $lockedRental;
        });
    }

    /**
     * Catat realisasi transfer pengembalian dana (batal, kedaluwarsa parsial, maupun kelebihan bayar aktif) (A-10, A1, SYS-13).
     *
     * @throws ValidationException
     */
    public function realizeRefund(
        Rental $rental,
        User $admin,
        float $transferAmount,
        CarbonInterface $transferredAt,
        string $reference,
        ?string $notes = null
    ): Rental {
        $trimmedReference = trim($reference);
        if ($trimmedReference === '') {
            throw ValidationException::withMessages([
                'refund_reference' => 'Nomor referensi transfer refund wajib diisi.',
            ]);
        }

        $normalizedAmount = round($transferAmount, 2);

        return DB::transaction(function () use ($rental, $admin, $normalizedAmount, $transferredAt, $trimmedReference, $notes): Rental {
            // Ikuti urutan kunci global: Car -> Rental.
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_PICKED_UP,
                Rental::STATUS_RETURNED,
                Rental::STATUS_CANCELLED,
                Rental::STATUS_EXPIRED,
            ], true)) {
                throw ValidationException::withMessages([
                    'refund' => 'Realisasi pengembalian dana tidak tersedia pada status pesanan saat ini.',
                ]);
            }

            if (in_array($lockedRental->rental_status, [Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED], true)
                && $lockedRental->payment_status === Rental::PAYMENT_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['refund' => 'Rekonsiliasi bukti pembayaran yang belum diperiksa sebelum transfer refund.']);
            }

            if ($lockedRental->refund_status !== Rental::REFUND_PENDING) {
                throw ValidationException::withMessages([
                    'refund' => 'Pesanan ini tidak memiliki pengembalian dana berstatus menunggu transfer.',
                ]);
            }

            $totalCashReceived = round(
                max(0.0, (float) $lockedRental->paid_amount) + max(0.0, (float) $lockedRental->additional_charge_paid_amount),
                2
            );
            $alreadyRealized = max(0.0, round((float) $lockedRental->refund_realized_amount, 2));
            $remainingCashHeld = max(0.0, round($totalCashReceived - $alreadyRealized, 2));
            $pendingRefund = $lockedRental->pendingRefundBalance();

            if ($normalizedAmount <= 0 || $normalizedAmount > $remainingCashHeld || $normalizedAmount !== $pendingRefund) {
                throw ValidationException::withMessages([
                    'transfer_amount' => 'Nominal transfer refund harus sama persis dengan sisa hak refund (Rp '
                        .number_format($pendingRefund, 0, ',', '.')
                        .') dan tidak melebihi dana yang masih ditahan (Rp '
                        .number_format($remainingCashHeld, 0, ',', '.').').',
                ]);
            }

            $lockedRental->refund_realized_amount = round($alreadyRealized + $normalizedAmount, 2);
            $lockedRental->refund_amount = round((float) $lockedRental->refund_realized_amount, 2);
            $lockedRental->refund_status = Rental::REFUND_PROCESSED;
            $lockedRental->refund_transferred_at = CarbonImmutable::instance($transferredAt)->timezone(RentalScheduleService::TIMEZONE);
            $lockedRental->refund_reference = $trimmedReference;
            $lockedRental->refund_notes = is_string($notes) && trim($notes) !== '' ? trim($notes) : $lockedRental->refund_notes;
            $lockedRental->refund_processed_by = $admin->id;
            $lockedRental->status_notice = 'Pengembalian dana sebesar Rp '.number_format($normalizedAmount, 0, ',', '.')
                .' telah ditransfer (Ref: '.$trimmedReference.').';
            $lockedRental->saveOrFail();

            // Catat kas keluar refund pada tanggal realisasi transfer (WIB) agar mengurangi penerimaan kas periode tersebut (A-12).
            RentalCashFlow::create([
                'rental_id' => $lockedRental->id,
                'recorded_by' => $admin->id,
                'direction' => RentalCashFlow::DIRECTION_OUTFLOW,
                'category' => RentalCashFlow::CATEGORY_RENTAL_REFUND,
                'amount' => $normalizedAmount,
                'occurred_at' => $lockedRental->refund_transferred_at,
                'reference' => $trimmedReference,
                'notes' => $lockedRental->refund_notes,
            ]);

            $lockedRental->recordHistory(
                $admin,
                'refund_realized',
                Rental::REFUND_PENDING,
                Rental::REFUND_PROCESSED,
                'Transfer pengembalian dana direalisasikan.',
                [
                    'refund_amount' => $normalizedAmount,
                    'refund_reference' => $trimmedReference,
                    'refund_transferred_at' => $lockedRental->refund_transferred_at->toIso8601String(),
                ]
            );

            return $lockedRental;
        });
    }
}
