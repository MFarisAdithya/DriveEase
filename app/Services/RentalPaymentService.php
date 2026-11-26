<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalCashFlow;
use App\Models\RentalPayment;
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
        UploadedFile $proofFile
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
            return DB::transaction(function () use ($rental, $actor, $storedPath, $nowWib): RentalPayment {
                // Ikuti hierarki kunci global: Car -> releaseExpiredHolds -> Rental.
                Car::query()->lockForUpdate()->findOrFail($rental->car_id);
                $this->scheduleService->releaseExpiredHolds($rental->car_id, $nowWib);

                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
                if ($lockedRental->car_id !== $rental->car_id) {
                    throw ValidationException::withMessages(['payment_proof' => 'Unit pesanan baru berubah. Muat ulang lalu kirim bukti kembali.']);
                }

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

                if (! $lockedRental->canUploadPaymentProof($nowWib)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Batas waktu unggah bukti pembayaran telah berakhir.',
                    ]);
                }

                // Simpan snapshot sisa tagihan sistem pada kolom historis
                // claimed_amount. Nominal kiriman browser tidak menjadi sumber data.
                $amountToClaim = $lockedRental->remainingBalance();

                if ($amountToClaim <= 0) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'Pesanan tidak memiliki sisa tagihan.',
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
     * Verifikasi pelunasan uang masuk, catat arus kas pada tanggal dana diterima (WIB), dan konfirmasikan pemesanan (A-07, A-12, SYS-07, SYS-13).
     *
     * @throws ValidationException
     */
    public function approvePayment(
        RentalPayment $payment,
        User $admin,
        float $verifiedAmount,
        ?CarbonInterface $receivedAt = null
    ): Rental {
        if ($receivedAt === null) {
            throw ValidationException::withMessages([
                'received_at' => 'Tanggal aktual dana diterima (WIB) wajib diisi.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $receivedAtWib = CarbonImmutable::instance($receivedAt)->timezone(RentalScheduleService::TIMEZONE);
        $normalizedAmount = round($verifiedAmount, 2);

        return DB::transaction(function () use ($payment, $admin, $normalizedAmount, $receivedAtWib, $nowWib): Rental {
            $carId = (int) Rental::query()->whereKey($payment->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $this->scheduleService->releaseExpiredHolds($carId, $nowWib);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($payment->rental_id);
            // Perubahan jadwal dapat memindahkan unit sebelum lock kendaraan
            // didapat. Tolak permintaan yang memakai unit lama untuk percobaan ulang.
            if ($lockedRental->car_id !== $carId) {
                throw ValidationException::withMessages(['payment' => 'Unit pesanan baru berubah. Muat ulang dan periksa bukti kembali.']);
            }
            $lockedPayment = RentalPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->isPendingApproval()) {
                throw ValidationException::withMessages([
                    'payment' => 'Bukti pembayaran ini sudah pernah diproses sebelumnya.',
                ]);
            }

            if (in_array($lockedRental->rental_status, [Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED], true)) {
                if ($normalizedAmount <= 0) {
                    throw ValidationException::withMessages(['verified_amount' => 'Nominal diterima harus lebih besar dari nol.']);
                }

                return $this->reconcileClosed($lockedRental, $lockedPayment, $admin, $normalizedAmount, $receivedAtWib, $nowWib);
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
                'received_at' => $receivedAtWib,
                'rejection_reason' => null,
            ]);

            // Catat penerimaan kas sewa utama berdasarkan tanggal aktual dana diterima (WIB), bukan tanggal verifikasi admin.
            RentalCashFlow::create([
                'rental_id' => $lockedRental->id,
                'rental_payment_id' => $lockedPayment->id,
                'recorded_by' => $admin->id,
                'direction' => RentalCashFlow::DIRECTION_INFLOW,
                'category' => RentalCashFlow::CATEGORY_MAIN_PAYMENT,
                'amount' => $normalizedAmount,
                'occurred_at' => $receivedAtWib,
                'notes' => 'Verifikasi pelunasan sewa utama (termasuk kelebihan bayar jika ada).',
            ]);

            $lockedRental->paid_amount = round((float) $lockedRental->paid_amount + $normalizedAmount, 2);
            $lockedRental->payment_verified_by = $admin->id;
            $lockedRental->payment_verified_at = $nowWib;
            $lockedRental->payment_rejection_reason = null;
            $lockedRental->transitionPaymentStatus(Rental::PAYMENT_PAID);

            $excessAmount = max(0.0, round($normalizedAmount - $remaining, 2));
            if ($lockedRental->identity_status !== null && $lockedRental->pickup_at?->lte($nowWib)) {
                $lockedRental->review_followup_at ??= $nowWib;
            }
            $confirmed = $lockedRental->confirmWhenReady($nowWib);
            $notice = $confirmed ? $lockedRental->status_notice
                : 'Pembayaran lunas. Pesanan masih memerlukan verifikasi identitas atau tindak lanjut admin.';

            if ($excessAmount > 0) {
                $lockedRental->overpayment_amount = round((float) $lockedRental->overpayment_amount + $excessAmount, 2);
                $lockedRental->refund_amount = round((float) $lockedRental->refund_amount + $excessAmount, 2);
                $lockedRental->refund_status = Rental::REFUND_PENDING;
                $lockedRental->refund_notes = trim(($lockedRental->refund_notes ? $lockedRental->refund_notes.' ' : '')
                    .'Kelebihan bayar sewa utama sebesar Rp '.number_format($excessAmount, 0, ',', '.').'.');
                $notice .= ' Terdapat kelebihan pembayaran sebesar Rp '.number_format($excessAmount, 0, ',', '.')
                    .' yang ditetapkan untuk dikembalikan oleh admin.';
            }

            $lockedRental->status_notice = $notice;
            $lockedRental->saveOrFail();

            $lockedRental->recordHistory(
                $admin,
                'payment_approved',
                "{$previousRentalStatus}/{$previousPaymentStatus}",
                $lockedRental->rental_status.'/'.Rental::PAYMENT_PAID,
                $confirmed ? 'Pembayaran dan identitas memenuhi syarat konfirmasi.' : 'Pembayaran diverifikasi lunas.',
                [
                    'payment_id' => $lockedPayment->id,
                    'verified_amount' => $normalizedAmount,
                    'received_at' => $receivedAtWib->toIso8601String(),
                    'overpayment_amount' => $excessAmount,
                    'total_paid_amount' => (float) $lockedRental->paid_amount,
                ]
            );

            return $lockedRental;
        });
    }

    /**
     * Tolak bukti transfer dengan alasan wajib serta pencatatan dana parsial dan tanggal penerimaan aktual jika sudah masuk (A-07, A-12, SYS-06).
     *
     * @throws ValidationException
     */
    public function rejectPayment(
        RentalPayment $payment,
        User $admin,
        string $reason,
        float $receivedAmount = 0.0,
        ?CarbonInterface $receivedAt = null
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

        if ($normalizedReceived > 0 && $receivedAt === null) {
            throw ValidationException::withMessages([
                'received_at' => 'Tanggal aktual dana diterima (WIB) wajib diisi apabila terdapat dana masuk sebagian.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $receivedAtWib = $receivedAt !== null
            ? CarbonImmutable::instance($receivedAt)->timezone(RentalScheduleService::TIMEZONE)
            : null;

        return DB::transaction(function () use ($payment, $admin, $trimmedReason, $normalizedReceived, $receivedAtWib, $nowWib): Rental {
            $carId = (int) Rental::query()->whereKey($payment->rental_id)->value('car_id');
            Car::query()->lockForUpdate()->findOrFail($carId);
            $this->scheduleService->releaseExpiredHolds($carId, $nowWib);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($payment->rental_id);
            // Perubahan jadwal dapat memindahkan unit sebelum lock kendaraan
            // didapat. Tolak permintaan yang memakai unit lama untuk percobaan ulang.
            if ($lockedRental->car_id !== $carId) {
                throw ValidationException::withMessages(['payment' => 'Unit pesanan baru berubah. Muat ulang dan periksa bukti kembali.']);
            }
            $lockedPayment = RentalPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->isPendingApproval()) {
                throw ValidationException::withMessages([
                    'payment' => 'Bukti pembayaran ini sudah pernah diproses sebelumnya.',
                ]);
            }

            if (in_array($lockedRental->rental_status, [Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED], true)) {
                return $this->reconcileClosed($lockedRental, $lockedPayment, $admin, $normalizedReceived, $receivedAtWib, $nowWib, $trimmedReason);
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
                'received_at' => $normalizedReceived > 0 ? $receivedAtWib : null,
            ]);

            if ($normalizedReceived > 0 && $receivedAtWib !== null) {
                $lockedRental->paid_amount = round((float) $lockedRental->paid_amount + $normalizedReceived, 2);

                // Catat penerimaan kas parsial pada tanggal uang benar-benar masuk rekening (WIB).
                RentalCashFlow::create([
                    'rental_id' => $lockedRental->id,
                    'rental_payment_id' => $lockedPayment->id,
                    'recorded_by' => $admin->id,
                    'direction' => RentalCashFlow::DIRECTION_INFLOW,
                    'category' => RentalCashFlow::CATEGORY_MAIN_PAYMENT,
                    'amount' => $normalizedReceived,
                    'occurred_at' => $receivedAtWib,
                    'notes' => 'Pembayaran sebagian saat verifikasi bukti utama ditolak: '.$trimmedReason,
                ]);
            }

            // Kebijakan SKPL memberi tenggat perbaikan 1 jam sejak penolakan, tanpa melewati jadwal pengambilan.
            $oneHourFromRejection = $nowWib->addHour();
            $pickupWib = CarbonImmutable::instance($lockedRental->pickup_at)->timezone(RentalScheduleService::TIMEZONE);
            // Penolakan pertama membuka satu jendela perbaikan. Penolakan
            // berikutnya mempertahankan tenggat yang sudah dicatat.
            $fixDeadline = $lockedRental->payment_rejected_at !== null && $lockedRental->payment_deadline_at !== null
                ? $lockedRental->payment_deadline_at->copy()->timezone(RentalScheduleService::TIMEZONE)
                : $oneHourFromRejection->min($pickupWib);
            $lateReview = $lockedRental->identity_status !== null && ($fixDeadline->lte($nowWib)
                || ($fixDeadline->lt($oneHourFromRejection)
                    && RentalScheduleService::reviewOverdue($lockedRental->payment_submitted_at, $nowWib)));
            if ($lateReview) {
                $lockedRental->payment_review_blocked = true;
                $lockedRental->review_followup_at ??= $nowWib;
            }

            $lockedRental->transitionPaymentStatus(Rental::PAYMENT_REJECTED);
            $lockedRental->payment_rejected_at ??= $nowWib;
            $lockedRental->payment_rejection_reason = $trimmedReason;
            $lockedRental->payment_deadline_at = $fixDeadline;

            $newRemaining = $lockedRental->remainingBalance();
            $lockedRental->status_notice = 'Bukti pembayaran ditolak: '.$trimmedReason
                .' Sisa tagihan: Rp '.number_format($newRemaining, 0, ',', '.')
                .($lateReview ? '. Admin perlu menghubungi pelanggan untuk penyelesaian.'
                    : '. Unggah perbaikan sebelum '.$fixDeadline->format('d M Y H:i').' WIB.');
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
                    'received_at' => $receivedAtWib?->toIso8601String(),
                    'remaining_balance' => $newRemaining,
                    'fix_deadline_at' => $fixDeadline->toIso8601String(),
                ]
            );

            return $lockedRental;
        });
    }

    private function reconcileClosed(
        Rental $rental,
        RentalPayment $payment,
        User $admin,
        float $amount,
        ?CarbonInterface $receivedAt,
        CarbonInterface $at,
        ?string $reason = null,
    ): Rental {
        // Bukti masih punya status pending dan sudah dikunci oleh pemanggil.
        // Menutup bukti dan mencatat kas dalam transaksi yang sama mencegah
        // rekonsiliasi ganda sekaligus menjaga status pesanan tetap terminal.
        $hasFunds = $amount > 0;
        $payment->update([
            'status' => $hasFunds ? RentalPayment::STATUS_APPROVED : RentalPayment::STATUS_REJECTED,
            'verified_amount' => $amount,
            'verified_by' => $admin->id,
            'verified_at' => $at,
            'received_at' => $hasFunds ? $receivedAt : null,
            'rejection_reason' => $reason,
        ]);
        if ($hasFunds) {
            RentalCashFlow::create([
                'rental_id' => $rental->id,
                'rental_payment_id' => $payment->id,
                'recorded_by' => $admin->id,
                'direction' => RentalCashFlow::DIRECTION_INFLOW,
                'category' => RentalCashFlow::CATEGORY_MAIN_PAYMENT,
                'amount' => $amount,
                'occurred_at' => $receivedAt,
                'notes' => 'Rekonsiliasi dana pada pesanan yang sudah ditutup.',
            ]);
        }
        $rental->paid_amount = round((float) $rental->paid_amount + $amount, 2);
        $rental->transitionPaymentStatus($rental->remainingBalance() <= 0 ? Rental::PAYMENT_PAID : Rental::PAYMENT_REJECTED);
        app(RentalFinancialService::class)->refreshCancellationRefund($rental);
        $rental->saveOrFail();
        $rental->recordHistory($admin, 'payment_reconciled', null, $rental->payment_status, $reason, [
            'payment_id' => $payment->id,
            'received_amount' => $amount,
            'received_at' => $receivedAt?->toIso8601String(),
        ]);

        return $rental;
    }
}
