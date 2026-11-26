<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IdentityDocuments
{
    public function exists(?string $path): bool
    {
        return $path && Storage::disk('local')->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            try {
                if (! Storage::disk('local')->delete($path)) {
                    report(new RuntimeException('Gagal membersihkan berkas identitas.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $numbers
     * @param  array<string, UploadedFile|null>  $files  Foto langsung dari formulir admin.
     */
    public function submit(User $user, array $numbers, ?User $admin = null, array $files = []): void
    {
        $created = [];
        try {
            DB::transaction(function () use ($user, $numbers, $admin, $files, &$created): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($locked->isVerified()) {
                    throw new RuntimeException('Identitas sudah disetujui.');
                }
                app(RentalIdentityService::class)->recordSubmission(
                    $locked, $admin ?? $locked, CarbonImmutable::now(RentalScheduleService::TIMEZONE)
                );
                $old = [];
                foreach (['ktp', 'sim'] as $type) {
                    $temporary = session("temp_{$type}_image");
                    $field = "{$type}_image";
                    if (($file = $files[$field] ?? null) !== null) {
                        // Unggahan admin langsung ke disk privat, terpisah dari
                        // berkas sementara pada sesi portal pelanggan.
                        $destination = $file->store('identities', 'local');
                        if ($destination === false) {
                            throw new RuntimeException('Gagal menyimpan dokumen identitas.');
                        }
                        $created[] = $destination;
                        $old[] = $locked->{$field};
                        $locked->{$field} = $destination;
                    } elseif ($admin === null && $this->exists($temporary)) {
                        $destination = 'identities/'.Str::uuid().'.'.pathinfo($temporary, PATHINFO_EXTENSION);
                        $created[] = $destination;
                        if (! Storage::disk('local')->copy($temporary, $destination) || ! $this->exists($destination)) {
                            throw new RuntimeException('Gagal menyimpan dokumen identitas.');
                        }
                        $old[] = $locked->{$field};
                        $locked->{$field} = $destination;
                    }
                    if (! $this->exists($locked->{$field})) {
                        throw new RuntimeException('Dokumen identitas tidak tersedia.');
                    }
                }
                $locked->fill([
                    'ktp_number' => $numbers['ktp_number'] ?? $locked->ktp_number,
                    'sim_number' => $numbers['sim_number'] ?? $locked->sim_number, 'verification_status' => 'pending',
                    'rejection_reason' => null, 'verified_at' => null, 'verified_by' => null,
                ])->saveOrFail();
                DB::afterCommit(function () use ($old): void {
                    foreach ($old as $path) {
                        $this->delete($path);
                    }
                });
            });
        } catch (Throwable $exception) {
            foreach ($created as $path) {
                $this->delete($path);
            }
            throw $exception;
        }
        if ($admin !== null) {
            return;
        }

        foreach (['ktp', 'sim'] as $type) {
            $this->delete(session("temp_{$type}_image"));
            session()->forget(["temp_{$type}_image", "temp_{$type}_image_name"]);
        }
    }
}
