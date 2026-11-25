<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackupDriveEase extends Command
{
    /**
     * Nama dan argumen perintah Artisan untuk pencadangan terisolasi database dan berkas privat (NFR-10).
     *
     * @var string
     */
    protected $signature = 'driveease:backup {--output= : Direktori tujuan paket cadangan DriveEase}';

    /**
     * Deskripsi perintah pada daftar Artisan.
     *
     * @var string
     */
    protected $description = 'Ekspor cadangan database konsisten dan berkas privat beserta manifest SHA-256 tanpa menyimpan APP_KEY mentah';

    /**
     * Daftar 12 tabel inti aplikasi dalam urutan dependensi foreign key agar pemulihan terurut aman.
     *
     * @var list<string>
     */
    private const BACKUP_TABLES = [
        'roles',
        'users',
        'rental_settings',
        'car_types',
        'cars',
        'rentals',
        'rental_payments',
        'rental_change_requests',
        'rental_extensions',
        'rental_histories',
        'inspections',
        'rental_cash_flows',
    ];

    /**
     * Direktori berkas privat pada disk local yang wajib disertakan dalam paket cadangan (NFR-10).
     *
     * @var list<string>
     */
    private const PRIVATE_DIRECTORIES = [
        'identities',
        'payments',
        'inspections',
        'damages',
    ];

    /**
     * Jalankan proses pencadangan database terisolasi dan berkas privat beserta checksum integritasnya.
     */
    public function handle(): int
    {
        $nowWib = now('Asia/Jakarta');
        $customOutput = trim((string) $this->option('output'));
        $outputDir = $customOutput !== ''
            ? $this->normalizeAbsolutePath($customOutput)
            : $this->normalizeAbsolutePath(storage_path('app/backups/'.$nowWib->format('Y-m-d_His')));

        // Tolak tujuan cadangan di dalam public/ atau storage/app/public agar berkas privat & DB dump tidak pernah terekspos ke web.
        if ($this->isPublicDirectory($outputDir)) {
            $this->error('Direktori tujuan cadangan tidak boleh berada di dalam area publik web (public atau storage/app/public).');

            return self::FAILURE;
        }

        $databaseDir = $outputDir.DIRECTORY_SEPARATOR.'database';
        $filesDir = $outputDir.DIRECTORY_SEPARATOR.'files';

        try {
            $backupsRoot = $this->normalizeAbsolutePath(storage_path('app/backups'));
            if (str_starts_with(strtolower($outputDir), strtolower($backupsRoot))) {
                File::ensureDirectoryExists($backupsRoot);
                $this->writeDenyHtaccess($backupsRoot);
            }

            File::ensureDirectoryExists($databaseDir);
            File::ensureDirectoryExists($filesDir);
            $this->writeDenyHtaccess($outputDir);

            // Ambil cuplikan seluruh tabel dalam satu transaksi READ ONLY REPEATABLE READ agar antar-tabel konsisten.
            $tableSnapshot = $this->readConsistentTableSnapshot();

            $tableManifest = [];
            foreach (self::BACKUP_TABLES as $table) {
                $rows = $tableSnapshot[$table] ?? [];
                $relativeJsonPath = "database/{$table}.json";
                $targetJsonPath = $outputDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR."{$table}.json";
                $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                File::put($targetJsonPath, $encoded);

                $tableManifest[$table] = [
                    'file' => $relativeJsonPath,
                    'row_count' => count($rows),
                    'sha256' => hash_file('sha256', $targetJsonPath),
                ];
            }

            $localDisk = Storage::disk('local');
            $fileManifest = [];

            foreach (self::PRIVATE_DIRECTORIES as $directory) {
                if (! $localDisk->exists($directory)) {
                    continue;
                }

                foreach ($localDisk->allFiles($directory) as $relativePath) {
                    $normalizedPath = str_replace('\\', '/', $relativePath);
                    $content = $localDisk->get($relativePath);
                    if ($content === null) {
                        continue;
                    }

                    $destinationPath = $filesDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
                    File::ensureDirectoryExists(dirname($destinationPath));
                    File::put($destinationPath, $content);

                    $fileManifest[$normalizedPath] = [
                        'backup_file' => 'files/'.$normalizedPath,
                        'size' => strlen($content),
                        'sha256' => hash_file('sha256', $destinationPath),
                    ];
                }
            }

            $appKey = (string) config('app.key');
            $manifest = [
                'generated_at' => $nowWib->toIso8601String(),
                'timezone' => 'Asia/Jakarta',
                'app_env' => (string) config('app.env'),
                'database_connection' => (string) config('database.default'),
                // Hanya sidik jari SHA-256 yang dicatat; nilai APP_KEY mentah wajib disimpan terpisah.
                'app_key_fingerprint' => hash('sha256', $appKey),
                'key_recovery_procedure' => 'Simpan APP_KEY pada pengelola rahasia terpisah dari paket cadangan. Saat pemulihan, pulihkan APP_KEY ke environment server terlebih dahulu, impor database dan salin direktori storage/app/private, lalu jalankan php artisan driveease:verify-restore --target-db=<db_terisolasi> --target-storage=<dir_privat_terisolasi>.',
                'tables' => $tableManifest,
                'files' => $fileManifest,
            ];

            $manifestPath = $outputDir.DIRECTORY_SEPARATOR.'manifest.json';
            File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $this->info('Cadangan DriveEase berhasil dibuat pada: '.$outputDir);
            $this->line('Tabel: '.count($tableManifest).' | Berkas privat: '.count($fileManifest));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Proses pencadangan gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Baca seluruh tabel cadangan dalam satu snapshot transaksi konsisten (REPEATABLE READ READ ONLY pada PostgreSQL).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function readConsistentTableSnapshot(): array
    {
        $exportRows = function (): array {
            $snapshot = [];
            foreach (self::BACKUP_TABLES as $table) {
                // DB::table() membaca nilai mentah kolom langsung dari PostgreSQL agar hash kata sandi
                // dan ciphertext nomor KTP/SIM tetap utuh terenkripsi di dalam file cadangan.
                $snapshot[$table] = DB::table($table)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $row): array => (array) $row)
                    ->all();
            }

            return $snapshot;
        };

        // Jika belum berada di dalam transaksi aktif (misalnya dari CLI/scheduler nyata), buka snapshot REPEATABLE READ READ ONLY.
        // Ketika dijalankan di dalam test DatabaseTransactions (transactionLevel > 0), baca di transaksi berjalan agar bebas SQLSTATE 25001.
        if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 0) {
            return DB::transaction(function () use ($exportRows): array {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');

                return $exportRows();
            });
        }

        return $exportRows();
    }

    /**
     * Tulis berkas .htaccess penolak akses HTTP langsung untuk melindungi paket cadangan apabila berada di bawah root web server.
     */
    private function writeDenyHtaccess(string $directory): void
    {
        $htaccessPath = $directory.DIRECTORY_SEPARATOR.'.htaccess';
        if (! File::exists($htaccessPath)) {
            File::put($htaccessPath, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
    }

    /**
     * Normalisasi path absolut beserta segmen .. sebelum memeriksa batas direktori publik.
     */
    private function normalizeAbsolutePath(string $path): string
    {
        $candidate = $path;
        if (! Str::startsWith($candidate, ['/', '\\']) && preg_match('/^[A-Za-z]:/', $candidate) !== 1) {
            $candidate = base_path($candidate);
        }

        $unified = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        $prefix = '';
        if (preg_match('/^([A-Za-z]:)/', $unified, $matches) === 1) {
            $prefix = $matches[1];
            $unified = substr($unified, 2);
        }

        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $unified), fn (string $part): bool => $part !== '' && $part !== '.');
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $leadingSeparator = $prefix === '' && Str::startsWith($candidate, ['/', '\\']) ? DIRECTORY_SEPARATOR : ($prefix !== '' ? DIRECTORY_SEPARATOR : '');

        return $prefix.$leadingSeparator.implode(DIRECTORY_SEPARATOR, $resolved);
    }

    /**
     * Periksa apakah path target berada di dalam direktori publik web yang dapat diakses tanpa autentikasi.
     */
    private function isPublicDirectory(string $normalizedPath): bool
    {
        $candidate = strtolower(rtrim(str_replace('\\', '/', $normalizedPath), '/'));
        $forbiddenRoots = [
            strtolower(rtrim(str_replace('\\', '/', public_path()), '/')),
            strtolower(rtrim(str_replace('\\', '/', storage_path('app/public')), '/')),
        ];

        foreach ($forbiddenRoots as $root) {
            if ($candidate === $root || str_starts_with($candidate, $root.'/')) {
                return true;
            }
        }

        return false;
    }
}
