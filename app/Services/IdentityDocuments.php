<?php

namespace App\Services;

use App\Models\User;
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

    /** @param array<string, mixed> $numbers */
    public function submit(User $user, array $numbers): void
    {
        $created = [];
        try {
            DB::transaction(function () use ($user, $numbers, &$created): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($locked->isVerified()) {
                    throw new RuntimeException('Identitas sudah disetujui.');
                }
                $old = [];
                foreach (['ktp', 'sim'] as $type) {
                    $temporary = session("temp_{$type}_image");
                    $field = "{$type}_image";
                    if ($this->exists($temporary)) {
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
                    'ktp_number' => $numbers['ktp_number'], 'nik_ktp' => $numbers['ktp_number'],
                    'sim_number' => $numbers['sim_number'], 'verification_status' => 'pending',
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
        foreach (['ktp', 'sim'] as $type) {
            $this->delete(session("temp_{$type}_image"));
            session()->forget(["temp_{$type}_image", "temp_{$type}_image_name"]);
        }
    }
}
