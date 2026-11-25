<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalFinancialService
{
    /**
     * Calculate refund percentage and capped refund amount according to SKPL cancellation policy (SYS-11).
     *
     * @return array{percentage: int, amount: float, paid_amount: float, reason_label: string}
     */
    public function calculateRefundPolicy(
        Rental $rental,
        string $cancellationType,
        ?CarbonInterface $cancelledAt = null
    ): array {
        $paidAmount = max(0.0, round((float) $rental->paid_amount, 2));
        $reference = $cancelledAt
            ? CarbonImmutable::instance($cancelledAt)->timezone(RentalScheduleService::TIMEZONE)
            : CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $pickupWib = CarbonImmutable::instance($rental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);

        if ($cancellationType === Rental::CANCEL_RENTAL_FAULT) {
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

        // Pengembalian dana dibatasi oleh dana yang benar-benar diterima (A-10, SYS-11).
        $amount = min($paidAmount, round($paidAmount * ($percentage / 100), 2));

        return [
            'percentage' => $percentage,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'reason_label' => $label,
        ];
    }

    /**
     * Calculate combined additional charges from late penalty, fuel shortage, and damage fee (SYS-11).
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
     * Record a customer's cancellation request after payment proof submission or confirmation (C-08).
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
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Pengajuan pembatalan hanya dapat dilakukan sebelum kendaraan diambil.',
                ]);
            }

            if ($lockedRental->canBeCancelledUnpaid()) {
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
     * Cancel a booking as admin and compute refund entitlement capped at paid_amount (A-06, A-10, SYS-11).
     *
     * @throws ValidationException
     */
    public function cancelWithRefund(
        Rental $rental,
        User $admin,
        string $cancellationType,
        string $reason,
        ?float $overrideRefundAmount = null
    ): Rental {
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

        return DB::transaction(function () use ($rental, $admin, $cancellationType, $trimmedReason, $overrideRefundAmount, $nowWib): Rental {
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($lockedRental->rental_status, [Rental::STATUS_PENDING, Rental::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'rental_status' => 'Hanya pesanan berstatus menunggu atau terkonfirmasi yang dapat dibatalkan.',
                ]);
            }

            $policyReference = $cancellationType === Rental::CANCEL_CUSTOMER && $lockedRental->cancellation_requested_at !== null
                ? $lockedRental->cancellation_requested_at
                : $nowWib;

            $policy = $this->calculateRefundPolicy($lockedRental, $cancellationType, $policyReference);
            $paidAmount = $policy['paid_amount'];
            $refundAmount = $overrideRefundAmount !== null ? round($overrideRefundAmount, 2) : $policy['amount'];

            // Refund tidak pernah boleh negatif maupun melebihi dana yang sudah diterima (A-10, SYS-11).
            if ($refundAmount < 0 || $refundAmount > $paidAmount) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Nominal pengembalian dana tidak boleh melebihi dana yang diterima (Rp '.number_format($paidAmount, 0, ',', '.').').',
                ]);
            }

            $previousStatus = $lockedRental->rental_status;
            $refundStatus = $refundAmount > 0 ? Rental::REFUND_PENDING : Rental::REFUND_NONE;

            $lockedRental->transitionRentalStatus(
                Rental::STATUS_CANCELLED,
                $refundAmount > 0
                    ? 'Pesanan dibatalkan. Hak pengembalian dana sebesar Rp '.number_format($refundAmount, 0, ',', '.').' sedang diproses admin.'
                    : 'Pesanan dibatalkan tanpa pengembalian dana sesuai ketentuan.'
            );
            $lockedRental->cancelled_at = $nowWib;
            $lockedRental->cancellation_type = $cancellationType;
            $lockedRental->cancellation_reason = $trimmedReason;
            $lockedRental->refund_percentage = $policy['percentage'];
            $lockedRental->refund_amount = $refundAmount;
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
                    'refund_percentage' => $policy['percentage'],
                    'refund_amount' => $refundAmount,
                    'refund_status' => $refundStatus,
                ]
            );

            return $lockedRental;
        });
    }

    /**
     * Record the actual refund transfer, date, and reference number (A-10, SYS-13).
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
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if ($lockedRental->rental_status !== Rental::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'refund' => 'Realisasi pengembalian dana hanya dapat dicatat pada pesanan yang sudah dibatalkan.',
                ]);
            }

            if ($lockedRental->refund_status !== Rental::REFUND_PENDING) {
                throw ValidationException::withMessages([
                    'refund' => 'Pesanan ini tidak memiliki pengembalian dana berstatus menunggu transfer.',
                ]);
            }

            $paidAmount = max(0.0, round((float) $lockedRental->paid_amount, 2));
            if ($normalizedAmount <= 0 || $normalizedAmount > $paidAmount) {
                throw ValidationException::withMessages([
                    'transfer_amount' => 'Nominal transfer refund harus lebih dari 0 dan tidak melebihi dana diterima (Rp '.number_format($paidAmount, 0, ',', '.').').',
                ]);
            }

            $lockedRental->refund_amount = $normalizedAmount;
            $lockedRental->refund_status = Rental::REFUND_PROCESSED;
            $lockedRental->refund_transferred_at = CarbonImmutable::instance($transferredAt)->timezone(RentalScheduleService::TIMEZONE);
            $lockedRental->refund_reference = $trimmedReference;
            $lockedRental->refund_notes = is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
            $lockedRental->refund_processed_by = $admin->id;
            $lockedRental->status_notice = 'Pengembalian dana sebesar Rp '.number_format($normalizedAmount, 0, ',', '.')
                .' telah ditransfer (Ref: '.$trimmedReference.').';
            $lockedRental->saveOrFail();

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
