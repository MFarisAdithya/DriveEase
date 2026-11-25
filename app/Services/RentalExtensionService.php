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
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            $car = Car::query()->lockForUpdate()->findOrFail($lockedRental->car_id);

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
     * Setujui pengajuan perpanjangan oleh admin setelah pemeriksaan ketersediaan tahap pertama (A-08, SYS-09).
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

        if (! (RentalSetting::current()?->hasValidPaymentAccount() ?? false)) {
            throw ValidationException::withMessages([
                'extension_id' => 'Rekening pembayaran rental belum dikonfigurasi dengan lengkap.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
        $trimmedNotes = trim((string) $notes);

        return DB::transaction(function () use ($extension, $admin, $trimmedNotes, $nowWib): RentalExtension {
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($lockedExtension->rental_id);
            $car = Car::query()->lockForUpdate()->findOrFail($lockedRental->car_id);

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
            // Sesuai A-08 dan SYS-09, jadwal baru baru aktif setelah pembayaran tambahan lunas diverifikasi admin.
            $twoHoursDeadline = $nowWib->addHours(2);
            $paymentDeadline = $twoHoursDeadline->lessThan($proposedReturnAt) ? $twoHoursDeadline : $proposedReturnAt;

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_AWAITING_PAYMENT,
                'approved_by' => $admin->id,
                'approved_at' => $nowWib,
                'payment_deadline_at' => $paymentDeadline,
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
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($lockedExtension->rental_id);

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
                $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);
                $lockedRental = Rental::query()->lockForUpdate()->findOrFail($lockedExtension->rental_id);

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
     * Verifikasi pelunasan perpanjangan oleh admin dan aktifkan jadwal pengembalian baru setelah cek ulang ketersediaan (A-08, SYS-09).
     *
     * @throws ValidationException
     */
    public function approveExtensionPayment(RentalExtension $extension, User $admin): RentalExtension
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'extension_id' => 'Hanya admin yang berhak memverifikasi pembayaran perpanjangan.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($extension, $admin, $nowWib): RentalExtension {
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($lockedExtension->rental_id);
            $car = Car::query()->lockForUpdate()->findOrFail($lockedRental->car_id);

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

            $proposedReturnAt = CarbonImmutable::instance($lockedExtension->proposed_return_at)
                ->timezone(RentalScheduleService::TIMEZONE);

            // Pemeriksaan ketersediaan tahap 2 (SYS-09): sebelum jadwal kembali baru diaktifkan,
            // sistem memeriksa ulang agar rentang tambahan belum diambil pesanan lain selama menunggu transfer.
            $this->scheduleService->releaseExpiredHolds($car->id, $nowWib);
            if (! $this->scheduleService->isCarAvailableForPeriod($car, $lockedRental->pickup_at, $proposedReturnAt, $lockedRental->id)) {
                throw ValidationException::withMessages([
                    'extension_id' => 'Jadwal perpanjangan kini bentrok dengan pesanan lain yang sudah masuk. Jadwal awal dipertahankan.',
                ]);
            }

            $previousReturnAt = $lockedRental->return_at;
            $newTotalDays = $lockedRental->total_days + $lockedExtension->additional_days;
            $newTotalAmount = round((float) $lockedRental->total_amount + (float) $lockedExtension->additional_amount, 2);
            $newPaidAmount = round((float) $lockedRental->paid_amount + (float) $lockedExtension->additional_amount, 2);

            $lockedRental->forceFill([
                'return_at' => $proposedReturnAt,
                'end_date' => $proposedReturnAt->toDateString(),
                'total_days' => $newTotalDays,
                'total_amount' => $newTotalAmount,
                'paid_amount' => $newPaidAmount,
                'status_notice' => 'Pembayaran perpanjangan terverifikasi. Batas pengembalian baru berlaku hingga '.$proposedReturnAt->format('d M Y H:i').' WIB.',
            ])->save();

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_COMPLETED,
                'payment_verified_by' => $admin->id,
                'payment_verified_at' => $nowWib,
                'payment_rejection_reason' => null,
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_activated',
                $lockedRental->rental_status,
                $lockedRental->rental_status,
                'Pembayaran perpanjangan sewa terverifikasi dan jadwal kembali diperbarui.',
                [
                    'extension_id' => $lockedExtension->id,
                    'additional_days' => $lockedExtension->additional_days,
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
     * Tolak bukti pembayaran perpanjangan oleh admin dengan alasan wajib agar pelanggan dapat mengunggah ulang (A-08).
     *
     * @throws ValidationException
     */
    public function rejectExtensionPayment(RentalExtension $extension, User $admin, string $reason): RentalExtension
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Hanya admin yang berhak menolak bukti pembayaran perpanjangan.',
            ]);
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan bukti pembayaran perpanjangan wajib diisi.',
            ]);
        }

        $nowWib = CarbonImmutable::now(RentalScheduleService::TIMEZONE);

        return DB::transaction(function () use ($extension, $admin, $trimmedReason, $nowWib): RentalExtension {
            $lockedExtension = RentalExtension::query()->lockForUpdate()->findOrFail($extension->id);
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($lockedExtension->rental_id);

            if ($lockedExtension->status !== RentalExtension::STATUS_PAYMENT_SUBMITTED) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Hanya bukti pembayaran perpanjangan yang sedang menunggu verifikasi yang dapat ditolak.',
                ]);
            }

            $lockedExtension->forceFill([
                'status' => RentalExtension::STATUS_AWAITING_PAYMENT,
                'payment_verified_by' => $admin->id,
                'payment_verified_at' => $nowWib,
                'payment_rejection_reason' => $trimmedReason,
            ])->save();

            $lockedRental->forceFill([
                'status_notice' => 'Bukti pembayaran perpanjangan ditolak admin: '.$trimmedReason.'. Unggah bukti ulang sebelum tenggat berakhir.',
            ])->save();

            $lockedRental->recordHistory(
                $admin,
                'extension_payment_rejected',
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
