<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalIdentityService
{
    public function approve(User $customer, User $admin): bool
    {
        return DB::transaction(function () use ($customer, $admin): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($locked->isVerified()) {
                return true;
            }
            if (! $locked->hasSubmittedDocuments()) {
                return false;
            }
            if ($locked->isRejectedVerification() && $locked->identity_rejected_at !== null) {
                throw ValidationException::withMessages(['identity' => 'Periksa dokumen perbaikan sebelum menyetujui identitas yang ditolak.']);
            }

            $now = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
            $locked->forceFill([
                'verification_status' => 'verified',
                'verified_at' => $now,
                'verified_by' => $admin->id,
                'rejection_reason' => null,
                'identity_rejected_at' => null,
            ])->saveOrFail();

            foreach ($this->pendingRentals($locked) as $rental) {
                // Pesanan yang belum diperbaiki tepat waktu tetap perlu diproses
                // scheduler. Meluluskan profil tidak menghidupkan pesanan tersebut.
                if ($rental->identity_status === Rental::IDENTITY_REJECTED) {
                    continue;
                }
                if ($rental->pickup_at?->lte($now)) {
                    $rental->review_followup_at ??= $now;
                }
                $previous = $rental->rental_status;
                $rental->identity_status = Rental::IDENTITY_VERIFIED;
                $rental->confirmWhenReady($now);
                $rental->saveOrFail();
                $rental->recordHistory($admin, 'identity_approved', $previous, $rental->rental_status, 'Dokumen identitas disetujui.');
            }

            return true;
        });
    }

    public function reject(User $customer, User $admin, string $reason): void
    {
        DB::transaction(function () use ($customer, $admin, $reason): void {
            $locked = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($locked->isVerified()) {
                throw ValidationException::withMessages(['identity' => 'Identitas yang sudah disetujui tidak dapat ditolak melalui pemeriksaan ini.']);
            }
            if ($locked->isRejectedVerification() && $locked->identity_rejected_at !== null) {
                return;
            }

            $now = CarbonImmutable::now(RentalScheduleService::TIMEZONE);
            $locked->identity_rejected_at ??= $now;
            $locked->forceFill([
                'verification_status' => 'rejected',
                'rejection_reason' => $reason,
                'verified_at' => null,
                'verified_by' => null,
            ])->saveOrFail();

            foreach ($this->pendingRentals($locked) as $rental) {
                $fullWindow = $locked->identity_rejected_at->copy()->addHours(6);
                // Tenggat pertama melekat pada pesanan. Penolakan setelah unggah
                // perbaikan tidak memulai enam jam lagi, meski pesanan berbeda jadwalnya.
                $rental->identity_deadline_at ??= $fullWindow->min($rental->pickup_at);
                $late = $rental->identity_deadline_at->lte($now)
                    || ($rental->identity_deadline_at->equalTo($rental->pickup_at)
                        && RentalScheduleService::reviewOverdue($locked->identity_submitted_at, $now));

                $rental->identity_status = $late ? Rental::IDENTITY_FOLLOWUP : Rental::IDENTITY_REJECTED;
                if ($late) {
                    $rental->review_followup_at ??= $now;
                }
                $rental->saveOrFail();
                $rental->recordHistory($admin, 'identity_rejected', null, $rental->identity_status, $reason, [
                    'deadline_at' => $rental->identity_deadline_at->toIso8601String(),
                ]);
            }
        });
    }

    /**
     * Dipanggil saat dokumen pengganti disimpan dalam transaksi User yang terkunci.
     */
    public function recordSubmission(User $locked, User $actor, CarbonImmutable $now): void
    {
        $rentals = $this->pendingRentals($locked);
        $eligible = $rentals->reject(fn (Rental $rental): bool => $rental->identity_status === Rental::IDENTITY_REJECTED
            && $rental->identity_deadline_at?->lte($now));

        if ($rentals->isNotEmpty() && $eligible->isEmpty()) {
            throw ValidationException::withMessages(['identity' => 'Tenggat perbaikan identitas sudah terlewati. Hubungi admin untuk meninjau pesanan.']);
        }

        foreach ($eligible as $rental) {
            if ($rental->identity_status === Rental::IDENTITY_REJECTED || $rental->identity_status === Rental::IDENTITY_FOLLOWUP) {
                $rental->identity_status = Rental::IDENTITY_PENDING;
                $rental->saveOrFail();
                $rental->recordHistory($actor, 'identity_resubmitted', null, Rental::IDENTITY_PENDING, 'Dokumen identitas diperbarui.');
            }
        }

        if ($rentals->isEmpty()) {
            $locked->identity_rejected_at = null;
        }
        $locked->identity_submitted_at = $now;
    }

    /** @return Collection<int, Rental> */
    private function pendingRentals(User $customer): Collection
    {
        // Urutan id sama dengan proses kedaluwarsa. Pembayaran hanya membaca
        // snapshot Rental, sehingga tidak perlu mengambil kunci User terbalik.
        return $customer->rentals()->where('rental_status', Rental::STATUS_PENDING)
            ->whereNotNull('identity_status')->orderBy('id')->lockForUpdate()->get();
    }
}
