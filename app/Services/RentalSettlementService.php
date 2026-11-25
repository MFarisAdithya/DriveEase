<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\RentalSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RentalSettlementService
{
    public function __construct(
        private readonly RentalFinancialService $financialService,
    ) {}

    /**
     * Periksa denda otomatis serta tetapkan biaya BBM dan kerusakan dengan alasan dan bukti pendukung (A-09, SYS-11).
     *
     * @throws ValidationException
     */
    public function assessAdditionalCharges(
        Rental $rental,
        User $admin,
        float $fuelFee,
        ?string $fuelReason,
        float $damageFee,
        ?string $damageReason,
        ?UploadedFile $damageEvidence = null
    ): Rental {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'additional_charges' => 'Hanya admin yang berhak menetapkan biaya tambahan pascapengembalian.',
            ]);
        }

        $normalizedFuel = round($fuelFee, 2);
        $normalizedDamage = round($damageFee, 2);
        $trimmedFuelReason = trim((string) $fuelReason);
        $trimmedDamageReason = trim((string) $damageReason);

        if ($normalizedFuel < 0 || $normalizedDamage < 0) {
            throw ValidationException::withMessages([
                'additional_charges' => 'Nominal biaya BBM maupun biaya kerusakan tidak boleh bernilai negatif.',
            ]);
        }

        if ($normalizedFuel > 0 && mb_strlen($trimmedFuelReason) < 5) {
            throw ValidationException::withMessages([
                'fuel_fee_reason' => 'Alasan kekurangan BBM wajib diisi minimal 5 karakter apabila biaya BBM lebih dari 0.',
            ]);
        }

        if ($normalizedDamage > 0 && mb_strlen($trimmedDamageReason) < 5) {
            throw ValidationException::withMessages([
                'damage_fee_reason' => 'Alasan biaya kerusakan wajib diisi minimal 5 karakter apabila biaya kerusakan lebih dari 0.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $storedEvidencePath = null;

        if ($damageEvidence !== null) {
            $ext = $damageEvidence->getClientOriginalExtension() ?: 'jpg';
            $storedEvidencePath = 'inspections/damage/'.Str::uuid().'.'.$ext;
            if (! Storage::disk('local')->putFileAs('inspections/damage', $damageEvidence, basename($storedEvidencePath))) {
                throw new RuntimeException('Gagal menyimpan bukti foto kerusakan ke penyimpanan privat.');
            }
        }

        $previousEvidencePath = null;

        try {
            $updatedRental = DB::transaction(function () use (
                $rental,
                $admin,
                $normalizedFuel,
                $trimmedFuelReason,
                $normalizedDamage,
                $trimmedDamageReason,
                $storedEvidencePath,
                $nowWib,
                &$previousEvidencePath
            ): Rental {
                // Ikuti urutan kunci global: Car -> Rental.
                Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $lockedRental = Rental::query()->with(['returnInspection', 'pickupInspection'])->lockForUpdate()->findOrFail($rental->id);

                if ($lockedRental->rental_status !== Rental::STATUS_RETURNED || $lockedRental->returnInspection === null) {
                    throw ValidationException::withMessages([
                        'additional_charges' => 'Biaya tambahan hanya dapat ditetapkan setelah kendaraan dikembalikan dan melewati inspeksi akhir.',
                    ]);
                }

                if (in_array($lockedRental->additional_charge_status, [
                    Rental::ADDITIONAL_CHARGE_PAID,
                    Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL,
                ], true)) {
                    throw ValidationException::withMessages([
                        'additional_charges' => 'Tagihan tambahan sedang menunggu verifikasi atau sudah lunas sehingga tidak dapat diubah ulang.',
                    ]);
                }

                $effectiveEvidencePath = $storedEvidencePath
                    ?? $lockedRental->damage_evidence_path
                    ?? ($normalizedDamage > 0 ? $lockedRental->returnInspection->condition_photo : null);

                // Sesuai SKPL A-09, biaya kerusakan wajib disertai bukti pendukung (foto kerusakan atau foto inspeksi akhir).
                if ($normalizedDamage > 0 && ($effectiveEvidencePath === null || trim($effectiveEvidencePath) === '')) {
                    throw ValidationException::withMessages([
                        'damage_evidence' => 'Biaya kerusakan wajib disertai foto bukti pendukung kerusakan.',
                    ]);
                }

                $lateFee = max(0.0, round((float) $lockedRental->late_fee_amount, 2));
                $combinedTotal = $this->financialService->calculateAdditionalChargesTotal($lateFee, $normalizedFuel, $normalizedDamage);

                if ($combinedTotal > 0 && ! (RentalSetting::current()?->hasValidPaymentAccount() ?? false)) {
                    throw ValidationException::withMessages([
                        'additional_charges' => 'Rekening pembayaran rental belum dikonfigurasi dengan lengkap untuk menerima pelunasan tagihan tambahan.',
                    ]);
                }

                $alreadyPaid = max(0.0, round((float) $lockedRental->additional_charge_paid_amount, 2));
                if ($combinedTotal > 0 && $alreadyPaid >= $combinedTotal) {
                    $newStatus = Rental::ADDITIONAL_CHARGE_PAID;
                } elseif ($combinedTotal > 0) {
                    $newStatus = Rental::ADDITIONAL_CHARGE_UNPAID;
                } else {
                    $newStatus = Rental::ADDITIONAL_CHARGE_NONE;
                }

                if ($storedEvidencePath !== null && is_string($lockedRental->damage_evidence_path)
                    && $lockedRental->damage_evidence_path !== $lockedRental->returnInspection->condition_photo) {
                    $previousEvidencePath = $lockedRental->damage_evidence_path;
                }

                $lockedRental->forceFill([
                    'fuel_fee_amount' => $normalizedFuel,
                    'fuel_fee_reason' => $trimmedFuelReason !== '' ? $trimmedFuelReason : null,
                    'damage_fee_amount' => $normalizedDamage,
                    'damage_fee_reason' => $trimmedDamageReason !== '' ? $trimmedDamageReason : null,
                    'damage_evidence_path' => $effectiveEvidencePath,
                    'additional_charges_total' => $combinedTotal,
                    'additional_charges_assessed_at' => $nowWib,
                    'additional_charges_assessed_by' => $admin->id,
                    'additional_charge_status' => $newStatus,
                    'status_notice' => $combinedTotal > 0 && $newStatus === Rental::ADDITIONAL_CHARGE_UNPAID
                        ? 'Tagihan tambahan akhir sebesar Rp '.number_format($combinedTotal, 0, ',', '.').' telah ditetapkan. Silakan lakukan transfer dan unggah bukti pembayaran.'
                        : 'Pemeriksaan biaya tambahan selesai tanpa tagihan tertunggak. Transaksi siap ditutup oleh admin.',
                ])->saveOrFail();

                $lockedRental->recordHistory(
                    $admin,
                    'additional_charges_assessed',
                    $lockedRental->rental_status,
                    $lockedRental->rental_status,
                    'Penetapan denda dan biaya tambahan pascapengembalian.',
                    [
                        'late_fee_amount' => $lateFee,
                        'fuel_fee_amount' => $normalizedFuel,
                        'damage_fee_amount' => $normalizedDamage,
                        'additional_charges_total' => $combinedTotal,
                        'additional_charge_status' => $newStatus,
                    ]
                );

                return $lockedRental->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedEvidencePath !== null) {
                Storage::disk('local')->delete($storedEvidencePath);
            }

            throw $exception;
        }

        if (is_string($previousEvidencePath) && $previousEvidencePath !== '' && $previousEvidencePath !== $storedEvidencePath) {
            Storage::disk('local')->delete($previousEvidencePath);
        }

        return $updatedRental;
    }

    /**
     * Unggah bukti pembayaran tagihan tambahan secara privat oleh pelanggan pemilik atau admin (C-10, A-09, NFR-01, NFR-04).
     *
     * @throws ValidationException
     */
    public function submitAdditionalChargePayment(Rental $rental, User $actor, UploadedFile $proofFile): Rental
    {
        if (! $actor->isAdmin() && $rental->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'additional_charge_proof' => 'Anda tidak berhak mengirim bukti pembayaran tagihan tambahan untuk pesanan ini.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $ext = $proofFile->getClientOriginalExtension() ?: 'jpg';
        $storedPath = 'payments/additional/'.Str::uuid().'.'.$ext;

        if (! Storage::disk('local')->putFileAs('payments/additional', $proofFile, basename($storedPath))) {
            throw new RuntimeException('Gagal menyimpan bukti pembayaran tagihan tambahan ke penyimpanan privat.');
        }

        $previousProofPath = null;

        try {
            $updatedRental = DB::transaction(function () use ($rental, $actor, $storedPath, $nowWib, &$previousProofPath): Rental {
                // Ikuti urutan kunci global: Car -> Rental.
                Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

                if ($lockedRental->rental_status !== Rental::STATUS_RETURNED) {
                    throw ValidationException::withMessages([
                        'additional_charge_proof' => 'Bukti pembayaran tagihan tambahan hanya dapat diunggah setelah kendaraan dikembalikan.',
                    ]);
                }

                if ((float) $lockedRental->additional_charges_total <= 0
                    || ! in_array($lockedRental->additional_charge_status, [
                        Rental::ADDITIONAL_CHARGE_UNPAID,
                        Rental::ADDITIONAL_CHARGE_REJECTED,
                    ], true)) {
                    throw ValidationException::withMessages([
                        'additional_charge_proof' => 'Pesanan ini tidak memiliki tagihan tambahan yang menunggu pembayaran.',
                    ]);
                }

                $previousStatus = $lockedRental->additional_charge_status;
                $previousProofPath = $lockedRental->additional_charge_proof;

                $lockedRental->forceFill([
                    'additional_charge_proof' => $storedPath,
                    'additional_charge_submitted_at' => $nowWib,
                    'additional_charge_status' => Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL,
                    'additional_charge_rejection_reason' => null,
                    'status_notice' => 'Bukti pembayaran tagihan tambahan telah diunggah dan sedang menunggu verifikasi admin.',
                ])->saveOrFail();

                $lockedRental->recordHistory(
                    $actor,
                    'additional_charge_proof_submitted',
                    $previousStatus,
                    Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL,
                    'Bukti transfer pembayaran tagihan tambahan diunggah.',
                    [
                        'remaining_balance' => $lockedRental->remainingAdditionalChargeBalance(),
                    ]
                );

                return $lockedRental->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }

        if (is_string($previousProofPath) && $previousProofPath !== '' && $previousProofPath !== $storedPath) {
            Storage::disk('local')->delete($previousProofPath);
        }

        return $updatedRental;
    }

    /**
     * Verifikasi pelunasan tagihan tambahan dan catat referensi pembayaran resmi oleh admin (A-09).
     *
     * @throws ValidationException
     */
    public function approveAdditionalChargePayment(
        Rental $rental,
        User $admin,
        float $verifiedAmount,
        string $paymentReference
    ): Rental {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'additional_charge' => 'Hanya admin yang berhak memverifikasi pembayaran tagihan tambahan.',
            ]);
        }

        $trimmedRef = trim($paymentReference);
        if (mb_strlen($trimmedRef) < 3) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Nomor referensi pembayaran tagihan tambahan wajib diisi.',
            ]);
        }

        $normalizedAmount = round($verifiedAmount, 2);
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($rental, $admin, $normalizedAmount, $trimmedRef, $nowWib): Rental {
            // Ikuti urutan kunci global: Car -> Rental.
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if ($lockedRental->rental_status !== Rental::STATUS_RETURNED
                || $lockedRental->additional_charge_status !== Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'additional_charge' => 'Tidak ada bukti pembayaran tagihan tambahan yang sedang menunggu verifikasi.',
                ]);
            }

            $remaining = $lockedRental->remainingAdditionalChargeBalance();
            if ($normalizedAmount < $remaining) {
                throw ValidationException::withMessages([
                    'verified_amount' => 'Nominal terverifikasi kurang dari sisa tagihan tambahan (Rp '
                        .number_format($remaining, 0, ',', '.').').',
                ]);
            }

            $previousStatus = $lockedRental->additional_charge_status;
            $newPaid = round((float) $lockedRental->additional_charge_paid_amount + $normalizedAmount, 2);

            $lockedRental->forceFill([
                'additional_charge_paid_amount' => $newPaid,
                'additional_charge_status' => Rental::ADDITIONAL_CHARGE_PAID,
                'additional_charge_verified_by' => $admin->id,
                'additional_charge_verified_at' => $nowWib,
                'additional_charge_payment_reference' => $trimmedRef,
                'additional_charge_rejection_reason' => null,
                'status_notice' => 'Pembayaran tagihan tambahan terverifikasi lunas (Ref: '.$trimmedRef.'). Transaksi siap diselesaikan.',
            ])->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'additional_charge_paid',
                $previousStatus,
                Rental::ADDITIONAL_CHARGE_PAID,
                'Pelunasan tagihan tambahan diverifikasi admin.',
                [
                    'verified_amount' => $normalizedAmount,
                    'total_additional_paid' => $newPaid,
                    'payment_reference' => $trimmedRef,
                ]
            );

            return $lockedRental->refresh();
        });
    }

    /**
     * Tolak bukti pembayaran tagihan tambahan dengan alasan wajib dan catat dana masuk parsial bila ada (A-09).
     *
     * @throws ValidationException
     */
    public function rejectAdditionalChargePayment(
        Rental $rental,
        User $admin,
        string $reason,
        float $receivedAmount = 0.0
    ): Rental {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Hanya admin yang berhak menolak bukti pembayaran tagihan tambahan.',
            ]);
        }

        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan bukti pembayaran tagihan tambahan wajib diisi minimal 5 karakter.',
            ]);
        }

        $normalizedReceived = round($receivedAmount, 2);
        if ($normalizedReceived < 0) {
            throw ValidationException::withMessages([
                'received_amount' => 'Nominal dana yang diterima tidak boleh bernilai negatif.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($rental, $admin, $trimmedReason, $normalizedReceived, $nowWib): Rental {
            // Ikuti urutan kunci global: Car -> Rental.
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if ($lockedRental->rental_status !== Rental::STATUS_RETURNED
                || $lockedRental->additional_charge_status !== Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Tidak ada bukti pembayaran tagihan tambahan yang sedang menunggu verifikasi.',
                ]);
            }

            $remaining = $lockedRental->remainingAdditionalChargeBalance();
            if ($normalizedReceived >= $remaining) {
                throw ValidationException::withMessages([
                    'received_amount' => 'Nominal yang diterima sudah melunasi sisa tagihan tambahan. Gunakan tombol verifikasi lunas.',
                ]);
            }

            $previousStatus = $lockedRental->additional_charge_status;
            $newPaid = round((float) $lockedRental->additional_charge_paid_amount + $normalizedReceived, 2);

            $lockedRental->forceFill([
                'additional_charge_paid_amount' => $newPaid,
                'additional_charge_status' => Rental::ADDITIONAL_CHARGE_REJECTED,
                'additional_charge_rejection_reason' => $trimmedReason,
                'additional_charge_verified_by' => $admin->id,
                'additional_charge_verified_at' => $nowWib,
                'status_notice' => 'Bukti pembayaran tagihan tambahan ditolak: '.$trimmedReason
                    .'. Sisa tagihan tambahan Rp '.number_format($lockedRental->remainingAdditionalChargeBalance(), 0, ',', '.').'.',
            ])->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'additional_charge_rejected',
                $previousStatus,
                Rental::ADDITIONAL_CHARGE_REJECTED,
                $trimmedReason,
                [
                    'received_amount' => $normalizedReceived,
                    'remaining_balance' => $lockedRental->remainingAdditionalChargeBalance(),
                ]
            );

            return $lockedRental->refresh();
        });
    }

    /**
     * Tutup transaksi penyewaan menjadi completed setelah inspeksi akhir dan seluruh kewajiban pembayaran/refund tuntas (SYS-12, SYS-07).
     *
     * @throws ValidationException
     */
    public function completeRental(Rental $rental, User $admin, ?string $notes = null): Rental
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'completion' => 'Hanya admin yang berhak menyelesaikan transaksi penyewaan.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $trimmedNotes = trim((string) $notes);

        return DB::transaction(function () use ($rental, $admin, $trimmedNotes, $nowWib): Rental {
            // Ikuti urutan kunci global: Car -> Rental -> RentalExtension (ordered ASC).
            Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            $lockedRental = Rental::query()->with(['pickupInspection', 'returnInspection'])->lockForUpdate()->findOrFail($rental->id);
            $extensions = RentalExtension::query()
                ->where('rental_id', $lockedRental->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedRental->rental_status !== Rental::STATUS_RETURNED) {
                throw ValidationException::withMessages([
                    'completion' => 'Transaksi hanya dapat diselesaikan setelah kendaraan dikembalikan.',
                ]);
            }

            if ($lockedRental->pickupInspection === null || $lockedRental->returnInspection === null) {
                throw ValidationException::withMessages([
                    'completion' => 'Inspeksi awal pengambilan dan inspeksi akhir pengembalian wajib lengkap sebelum transaksi ditutup.',
                ]);
            }

            if ($lockedRental->payment_status !== Rental::PAYMENT_PAID || $lockedRental->remainingBalance() > 0) {
                throw ValidationException::withMessages([
                    'completion' => 'Pembayaran sewa utama belum lunas sepenuhnya.',
                ]);
            }

            if ($lockedRental->additional_charges_assessed_at === null) {
                throw ValidationException::withMessages([
                    'completion' => 'Admin wajib melakukan pemeriksaan dan penetapan biaya tambahan akhir terlebih dahulu sebelum menutup transaksi.',
                ]);
            }

            if ($lockedRental->remainingAdditionalChargeBalance() > 0
                || in_array($lockedRental->additional_charge_status, [
                    Rental::ADDITIONAL_CHARGE_UNPAID,
                    Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL,
                    Rental::ADDITIONAL_CHARGE_REJECTED,
                ], true)) {
                throw ValidationException::withMessages([
                    'completion' => 'Tagihan tambahan (denda keterlambatan, BBM, atau kerusakan) belum lunas diverifikasi.',
                ]);
            }

            if ($lockedRental->refund_status === Rental::REFUND_PENDING) {
                throw ValidationException::withMessages([
                    'completion' => 'Masih terdapat kewajiban pengembalian dana utama yang belum direalisasikan.',
                ]);
            }

            $hasBlockingExtension = $extensions->contains(function (RentalExtension $ext): bool {
                return $ext->isOpen() || $ext->refund_status === RentalExtension::REFUND_PENDING;
            });

            if ($hasBlockingExtension) {
                throw ValidationException::withMessages([
                    'completion' => 'Masih terdapat proses perpanjangan atau pengembalian dana perpanjangan yang belum selesai.',
                ]);
            }

            // Status transaksi ditutup menjadi completed tanpa memaksa status kesiapan unit mobil berubah (SYS-12).
            // Kendaraan yang rusak tetap bertahan di status perawatan/needs_maintenance hingga dinyatakan layak secara terpisah.
            $previousStatus = $lockedRental->rental_status;
            $lockedRental->transitionRentalStatus(
                Rental::STATUS_COMPLETED,
                'Transaksi penyewaan telah selesai dan seluruh kewajiban administrasi maupun keuangan tuntas.'
            );
            $lockedRental->completed_at = $nowWib;
            $lockedRental->completed_by = $admin->id;
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'rental_completed',
                $previousStatus,
                Rental::STATUS_COMPLETED,
                $trimmedNotes !== '' ? $trimmedNotes : 'Transaksi ditutup setelah inspeksi akhir dan seluruh kewajiban selesai.',
                [
                    'completed_at' => $nowWib->toIso8601String(),
                    'additional_charges_total' => (float) $lockedRental->additional_charges_total,
                    'additional_charge_payment_reference' => $lockedRental->additional_charge_payment_reference,
                ]
            );

            return $lockedRental->refresh();
        });
    }
}
