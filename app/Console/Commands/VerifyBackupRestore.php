<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Throwable;

class VerifyBackupRestore extends Command
{
    /**
     * Nama dan argumen perintah uji pemulihan terisolasi tanpa mengubah database aktif (NFR-10).
     *
     * @var string
     */
    protected $signature = 'driveease:verify-restore
        {path : Direktori paket cadangan hasil driveease:backup}
        {--rental-code= : Kode pesanan yang diuji pembacaan data dan berkas privatnya}';

    /**
     * Deskripsi perintah pada daftar Artisan.
     *
     * @var string
     */
    protected $description = 'Verifikasi integritas cadangan, kecocokan kunci enkripsi, dan keterbacaan satu transaksi secara terisolasi';

    /**
     * Verifikasi paket cadangan serta uji dekripsi dan ketersediaan berkas untuk satu transaksi.
     */
    public function handle(): int
    {
        $backupDir = rtrim((string) $this->argument('path'), '\\/');
        $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';

        if (! File::exists($manifestPath)) {
            $this->error('Berkas manifest.json tidak ditemukan pada direktori cadangan.');

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->error('Manifest cadangan tidak valid: '.$exception->getMessage());

            return self::FAILURE;
        }

        $expectedKeyFingerprint = hash('sha256', (string) config('app.key'));
        if (! hash_equals((string) ($manifest['app_key_fingerprint'] ?? ''), $expectedKeyFingerprint)) {
            $this->error('Sidik jari APP_KEY saat ini tidak cocok dengan paket cadangan sehingga data terenkripsi tidak dapat dipulihkan.');

            return self::FAILURE;
        }

        $tables = [];
        foreach (($manifest['tables'] ?? []) as $tableName => $meta) {
            $relativeJson = str_replace('/', DIRECTORY_SEPARATOR, (string) ($meta['file'] ?? ''));
            $fullJsonPath = $backupDir.DIRECTORY_SEPARATOR.$relativeJson;

            if ($relativeJson === '' || ! File::exists($fullJsonPath)) {
                $this->error("Berkas cadangan tabel {$tableName} tidak ditemukan.");

                return self::FAILURE;
            }

            $actualHash = hash_file('sha256', $fullJsonPath);
            if (! hash_equals((string) ($meta['sha256'] ?? ''), (string) $actualHash)) {
                $this->error("Checksum SHA-256 tabel {$tableName} tidak cocok (berkas rusak atau dimodifikasi).");

                return self::FAILURE;
            }

            $tables[$tableName] = json_decode((string) File::get($fullJsonPath), true, 512, JSON_THROW_ON_ERROR);
        }

        foreach (($manifest['files'] ?? []) as $relativePath => $meta) {
            $backupFileRel = str_replace('/', DIRECTORY_SEPARATOR, (string) ($meta['backup_file'] ?? ''));
            $fullBackupFile = $backupDir.DIRECTORY_SEPARATOR.$backupFileRel;

            if ($backupFileRel === '' || ! File::exists($fullBackupFile)) {
                $this->error("Berkas privat {$relativePath} hilang dari paket cadangan.");

                return self::FAILURE;
            }

            $actualFileHash = hash_file('sha256', $fullBackupFile);
            if (! hash_equals((string) ($meta['sha256'] ?? ''), (string) $actualFileHash)) {
                $this->error("Checksum berkas privat {$relativePath} tidak cocok.");

                return self::FAILURE;
            }
        }

        $rentals = collect($tables['rentals'] ?? []);
        if ($rentals->isEmpty()) {
            $this->error('Tabel rentals pada paket cadangan kosong sehingga uji satu transaksi tidak dapat dilakukan.');

            return self::FAILURE;
        }

        $requestedCode = trim((string) $this->option('rental-code'));
        $targetRental = $requestedCode !== ''
            ? $rentals->firstWhere('rental_code', $requestedCode)
            : $rentals->first();

        if (! is_array($targetRental)) {
            $this->error('Transaksi target dengan kode '.($requestedCode !== '' ? $requestedCode : '-').' tidak ditemukan di cadangan.');

            return self::FAILURE;
        }

        $users = collect($tables['users'] ?? []);
        $customer = $users->firstWhere('id', $targetRental['user_id'] ?? null);
        if (! is_array($customer)) {
            $this->error('Data pelanggan pemilik transaksi tidak ditemukan pada cadangan users.');

            return self::FAILURE;
        }

        // Uji dekripsi identitas terenkripsi tanpa mencetak nomor mentah ke layar/log.
        foreach (['ktp_number', 'nik_ktp', 'sim_number'] as $identityColumn) {
            $cipher = $customer[$identityColumn] ?? null;
            if (is_string($cipher) && $cipher !== '') {
                if (! $this->canDecryptValue($cipher)) {
                    $this->error("Kolom terenkripsi {$identityColumn} gagal didekripsi dengan APP_KEY aktif.");

                    return self::FAILURE;
                }
            }
        }

        // Kumpulkan seluruh berkas privat milik transaksi target untuk dipastikan hadir dalam paket cadangan.
        $referencedFiles = array_filter([
            $customer['ktp_image'] ?? null,
            $customer['sim_image'] ?? null,
            $targetRental['damage_evidence_path'] ?? null,
            $targetRental['additional_charge_proof'] ?? null,
        ]);

        foreach (($tables['rental_payments'] ?? []) as $payment) {
            if (($payment['rental_id'] ?? null) === $targetRental['id'] && ! empty($payment['payment_proof'])) {
                $referencedFiles[] = $payment['payment_proof'];
            }
        }

        foreach (($tables['inspections'] ?? []) as $inspection) {
            if (($inspection['rental_id'] ?? null) === $targetRental['id'] && ! empty($inspection['condition_photo'])) {
                $referencedFiles[] = $inspection['condition_photo'];
            }
        }

        foreach (array_unique($referencedFiles) as $relFile) {
            $normalized = str_replace('\\', '/', (string) $relFile);
            $expectedBackupFile = $backupDir.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);

            if (! File::exists($expectedBackupFile)) {
                $this->error("Berkas privat transaksi {$targetRental['rental_code']} ({$normalized}) tidak tersedia dalam paket cadangan.");

                return self::FAILURE;
            }
        }

        $this->info('Uji pemulihan terisolasi BERHASIL untuk transaksi '.$targetRental['rental_code'].' (berkas diperiksa: '.count(array_unique($referencedFiles)).').');

        return self::SUCCESS;
    }

    /**
     * Coba dekripsi ciphertext identitas Laravel baik berformat string mentah maupun serialized.
     */
    private function canDecryptValue(string $ciphertext): bool
    {
        try {
            $plain = Crypt::decryptString($ciphertext);

            return $plain !== '';
        } catch (Throwable) {
            try {
                $plain = (string) Crypt::decrypt($ciphertext);

                return $plain !== '';
            } catch (Throwable) {
                return false;
            }
        }
    }
}
