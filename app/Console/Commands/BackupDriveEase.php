<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
    protected $description = 'Ekspor cadangan database dan berkas privat beserta manifest SHA-256 tanpa menyimpan APP_KEY mentah';

    /**
     * Daftar tabel inti aplikasi dalam urutan dependensi relasi agar mudah direkonsiliasi saat pemulihan.
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
     * Jalankan proses pencadangan database dan berkas privat beserta checksum integritasnya.
     */
    public function handle(): int
    {
        $nowWib = now('Asia/Jakarta');
        $customOutput = trim((string) $this->option('output'));
        $outputDir = $customOutput !== ''
            ? $customOutput
            : storage_path('app/backups/'.$nowWib->format('Y-m-d_His'));

        $databaseDir = $outputDir.DIRECTORY_SEPARATOR.'database';
        $filesDir = $outputDir.DIRECTORY_SEPARATOR.'files';

        try {
            File::ensureDirectoryExists($databaseDir);
            File::ensureDirectoryExists($filesDir);

            $tableManifest = [];
            foreach (self::BACKUP_TABLES as $table) {
                // DB::table() membaca nilai mentah kolom langsung dari PostgreSQL agar hash kata sandi
                // dan ciphertext nomor KTP/SIM tetap utuh terenkripsi di dalam file cadangan.
                $rows = DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
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
                'key_recovery_procedure' => 'Simpan APP_KEY pada pengelola rahasia terpisah dari paket cadangan. Saat pemulihan, pulihkan APP_KEY ke environment server terlebih dahulu, impor database dan salin direktori storage/app/private, lalu jalankan php artisan driveease:verify-restore.',
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
}
