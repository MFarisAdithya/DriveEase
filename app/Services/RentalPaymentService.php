<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RentalPaymentService
{
    public function __construct(
        private readonly RentalScheduleService $scheduleService,
    ) {}

    /**
     * Simpan bukti transfer pelanggan atau walk-in secara privat dan perbarui status tanpa memperpanjang tenggat (C-06, SYS-06).
     *
     * @throws ValidationException
     */
    public function submitProof(
        Rental $rental,
        User $actor,
        UploadedFile $proofFile,
        ?float $claimedAmount = null
    ): RentalPayment {
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $extension = $proofFile->getClientOriginalExtension() ?: 'jpg';
        $storedPath = 'payments/booking/'.Str::uuid().'.'.$extension;

        // Simpan bukti transfer di disk 'local' (penyimpanan privat) sebelum masuk transaksi DB.
        // Apabila penyimpanan DB gagal, blok catch menghapus berkas baru agar keadaan tetap bersih.
        if (! Storage::disk('local')->putFileAs('payments/booking', $proofFile, basename($storedPath))) {
            throw new RuntimeException('Gagal menyimpan berkas bukti pembayaran ke penyimpanan privat.');
        }

        try {
            return DB::transaction(function () use ($rental, $actor, $claimedAmount, $storedPath, $nowWib): RentalPayment {
                // Ikuti hierarki kunci global: Car -> releaseExpiredHolds -> Rental.
                Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $this->scheduleService->releaseExpiredHolds($rental->car_id, $nowWib);

                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

                if ($lockedRental->rental_status !== Rental::STATUS_PENDING) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Bukti pembayaran hanya dapat diunggah pada pesanan yang menunggu pembayaran.',
                    ]);
                }

                if ($lockedRental->payment_status === Rental::PAYMENT_PENDING_APPROVAL) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Bukti pembayaran sebelumnya masih menunggu verifikasi admin.',
                    ]);
                }

                if (! in_array($lockedRental->payment_status, [Rental::PAYMENT_UNPAID, Rental::PAYMENT_REJECTED], true)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Pesanan ini tidak berada pada status yang membutuhkan unggahan pembayaran.',
                    ]);
                }

                if ($lockedRental->payment_deadline_at !== null && $lockedRental->payment_deadline_at->copy()->timezone(RentalScheduleService::TIMEZONE)->lte($nowWib)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Batas waktu unggah bukti pembayaran telah berakhir.',
                    ]);
                }

                $remainingBalance = $lockedRental->remainingBalance();
                $amountToClaim = $claimedAmount !== null ? round($claimedAmount, 2) : $remainingBalance;

                if ($amountToClaim <= 0) {
                    throw ValidationException::withMessages([
                        'claimed_amount' => 'Nominal pembayaran harus lebih besar dari 0.',
                    ]);
                }

                $previousPaymentStatus = $lockedRental->payment_status;
                $paymentType = ((float) $lockedRental->paid_amount) > 0
                    ? RentalPayment::TYPE_SHORTFALL
                    : RentalPayment::TYPE_BOOKING;

                $payment = RentalPayment::create([
                    'rental_id' => $lockedRental->id,
                    'uploaded_by' => $actor->id,
                    'payment_type' => $paymentType,
                    'claimed_amount' => $amountToClaim,
                    'proof_path' => $storedPath,
                    'status' => RentalPayment::STATUS_PENDING_APPROVAL,
                    'submitted_at' => $nowWib,
                ]);

                // Unggah ulang tidak pernah memperpanjang payment_deadline_at secara otomatis (SYS-06).
                $lockedRental->payment_proof = $storedPath;
                $lockedRental->payment_submitted_at = $nowWib;
                $lockedRental->transitionPaymentStatus(Rental::PAYMENT_PENDING_APPROVAL);
                $lockedRental->status_notice = 'Bukti pembayaran telah dikirim dan sedang menunggu verifikasi admin.';
                $lockedRental->saveOrFail();

                $lockedRental->recordHistory(
                    $actor,
                    'payment_proof_submitted',
                    $previousPaymentStatus,
                    Rental::PAYMENT_PENDING_APPROVAL,
                    'Bukti transfer diunggah untuk diverifikasi.',
                    [
                        'payment_id' => $payment->id,
                        'payment_type' => $paymentType,
                        'claimed_amount' => $amountToClaim,
                    ]
                );

                return $payment;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * Verifikasi pelunasan uang masuk dan konfirmasikan pemesanan (A-07, SYS-07, SYS-13).
     *
     * @throws ValidationException
     */
    public function approvePayment(RentalPayment $payment, User $admin, float $verifiedAmount): Rental
    {
        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $normalizedAmount = round($verifiedAmount, 2);

        return DB::transaction(function () use ($payment, $admin, $normalizedAmount, $nowWib): Rental {
            $carId = (int) Rental::query()->whereKey($payment->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($payment->rental_id);
            $lockedPayment = RentalPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->isPendingApproval()) {
                throw ValidationException::withMessages([
                    'payment' => 'Bukti pembayaran ini sudah pernah diproses sebelumnya.',
                ]);
            }

            if ($lockedRental->rental_status !== Rental::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'payment' => 'Pesanan tidak lagi berada pada status menunggu konfirmasi.',
                ]);
            }

            $remaining = $lockedRental->remainingBalance();
            if ($normalizedAmount < $remaining) {
                throw ValidationException::withMessages([
                    'verified_amount' => 'Nominal terverifikasi kurang dari sisa tagihan (Rp '.number_format($remaining, 0, ',', '.').'). Gunakan penolakan bukti dengan nominal diterima untuk meminta pelunasan selisih.',
                ]);
            }

            $previousPaymentStatus = $lockedRental->payment_status;
            $previousRentalStatus = $lockedRental->rental_status;

            $lockedPayment->update([
                'status' => RentalPayment::STATUS_APPROVED,
                'verified_amount' => $normalizedAmount,
                'verified_by' => $admin->id,
                'verified_at' => $nowWib,
                'rejection_reason' => null,
            ]);

            $lockedRental->paid_amount = round((float) $lockedRental->paid_amount + $normalizedAmount, 2);
            $lockedRental->payment_verified_by = $admin->id;
            $lockedRental->payment_verified_at = $nowWib;
            $lockedRental->payment_rejection_reason = null;
            $lockedRental->transitionPaymentStatus(Rental::PAYMENT_PAID);
            $lockedRental->transitionRentalStatus(
                Rental::STATUS_CONFIRMED,
                'Pembayaran lunas terverifikasi. Pesanan Anda telah dikonfirmasi dan siap diambil sesuai jadwal.'
            );
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'payment_approved',
                "{$previousRentalStatus}/{$previousPaymentStatus}",
                Rental::STATUS_CONFIRMED.'/'.Rental::PAYMENT_PAID,
                'Pembayaran diverifikasi lunas dan pesanan dikonfirmasi.',
                [
                    'payment_id' => $lockedPayment->id,
                    'verified_amount' => $normalizedAmount,
                    'total_paid_amount' => (float) $lockedRental->paid_amount,
                ]
            );

            return $lockedRental;
        });
    }

    /**
     * Tolak bukti transfer dengan alasan wajib serta pencatatan dana parsial jika sudah masuk (A-07, SYS-06).
     *
     * @throws ValidationException
     */
    public function rejectPayment(
        RentalPayment $payment,
        User $admin,
        string $reason,
        float $receivedAmount = 0.0
    ): Rental {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan bukti pembayaran wajib diisi minimal 5 karakter.',
            ]);
        }

        $normalizedReceived = round($receivedAmount, 2);
        if ($normalizedReceived < 0) {
            throw ValidationException::withMessages([
                'received_amount' => 'Nominal dana diterima tidak boleh bernilai negatif.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($payment, $admin, $trimmedReason, $normalizedReceived, $nowWib): Rental {
            $carId = (int) Rental::query()->whereKey($payment->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($payment->rental_id);
            $lockedPayment = RentalPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->isPendingApproval()) {
                throw ValidationException::withMessages([
                    'payment' => 'Bukti pembayaran ini sudah pernah diproses sebelumnya.',
                ]);
            }

            if ($lockedRental->rental_status !== Rental::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'payment' => 'Pesanan tidak lagi berada pada status menunggu konfirmasi.',
                ]);
            }

            $remaining = $lockedRental->remainingBalance();
            if ($normalizedReceived >= $remaining) {
                throw ValidationException::withMessages([
                    'received_amount' => 'Nominal dana yang diterima sudah melunasi seluruh sisa tagihan. Gunakan tombol setujui pembayaran.',
                ]);
            }

            $previousPaymentStatus = $lockedRental->payment_status;

            $lockedPayment->update([
                'status' => RentalPayment::STATUS_REJECTED,
                'verified_amount' => $normalizedReceived,
                'rejection_reason' => $trimmedReason,
                'verified_by' => $admin->id,
                'verified_at' => $nowWib,
            ]);

            if ($normalizedReceived > 0) {
                $lockedRental->paid_amount = round((float) $lockedRental->paid_amount + $normalizedReceived, 2);
            }

            // Kebijakan SKPL memberi tenggat perbaikan 1 jam sejak penolakan, tanpa melewati jadwal pengambilan.
            $oneHourFromRejection = $nowWib->addHour();
            $pickupWib = CarbonImmutable::instance($lockedRental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);
            $fixDeadline = $oneHourFromRejection->lessThan($pickupWib) ? $oneHourFromRejection : $pickupWib;

            $lockedRental->transitionPaymentStatus(Rental::PAYMENT_REJECTED);
            $lockedRental->payment_rejected_at = $nowWib;
            $lockedRental->payment_rejection_reason = $trimmedReason;
            $lockedRental->payment_deadline_at = $fixDeadline;

            $newRemaining = $lockedRental->remainingBalance();
            $lockedRental->status_notice = 'Bukti pembayaran ditolak: '.$trimmedReason
                .' Sisa tagihan: Rp '.number_format($newRemaining, 0, ',', '.')
                .'. Unggah perbaikan sebelum '.$fixDeadline->format('d M Y H:i').' WIB.';
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'payment_rejected',
                $previousPaymentStatus,
                Rental::PAYMENT_REJECTED,
                $trimmedReason,
                [
                    'payment_id' => $lockedPayment->id,
                    'received_amount' => $normalizedReceived,
                    'remaining_balance' => $newRemaining,
                    'fix_deadline_at' => $fixDeadline->toIso8601String(),
                ]
            );

            return $lockedRental;
        });
    }
}
