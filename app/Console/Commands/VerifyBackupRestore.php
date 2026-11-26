<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VerifyBackupRestore extends Command
{
    /**
     * Daftar 12 tabel inti sesuai urutan ketergantungan foreign key untuk proses impor nyata ke PostgreSQL terisolasi.
     *
     * @var list<string>
     */
    private const RESTORE_ORDER = [
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
     * Nama database yang dilarang keras dijadikan target restore otomatis agar database utama/sistem tetap aman.
     *
     * @var list<string>
     */
    private const FORBIDDEN_TARGET_DBS = [
        'driveease_db',
        'driveease_test',
        'postgres',
        'template0',
        'template1',
    ];

    /**
     * Nama dan argumen perintah uji pemulihan paket cadangan sekaligus pemulihan nyata ke database & storage terisolasi (NFR-10).
     *
     * @var string
     */
    protected $signature = 'driveease:verify-restore
        {path : Direktori paket cadangan hasil driveease:backup}
        {--rental-code= : Kode pesanan yang diuji pembacaan data dan berkas privatnya}
        {--target-db= : Nama database PostgreSQL terisolasi untuk uji restore sesungguhnya}
        {--target-storage= : Direktori penyimpanan privat terisolasi untuk uji pemulihan berkas}';

    /**
     * Deskripsi perintah pada daftar Artisan.
     *
     * @var string
     */
    protected $description = 'Verifikasi integritas paket cadangan dan jalankan latihan restore nyata ke database PostgreSQL serta storage privat terisolasi';

    /**
     * Verifikasi manifest/checksum, lakukan pemulihan database & berkas jika target diberikan, dan ukur durasi pemulihan.
     */
    public function handle(): int
    {
        $startHr = hrtime(true);
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

        // array_diff membandingkan daftar tabel wajib dengan nama yang ada di manifest.
        // Ketiadaan entri berarti tabel belum diverifikasi, meski berkas JSON masih ada.
        $manifestTables = $manifest['tables'] ?? null;
        $manifestFiles = $manifest['files'] ?? null;
        if (! is_array($manifestTables) || array_diff(self::RESTORE_ORDER, array_keys($manifestTables)) !== [] || ! is_array($manifestFiles)) {
            $this->error('Manifest cadangan tidak memuat daftar lengkap tabel atau inventaris berkas privat.');

            return self::FAILURE;
        }

        $tables = [];
        foreach (self::RESTORE_ORDER as $tableName) {
            $meta = $manifestTables[$tableName];
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

            $decodedRows = json_decode((string) File::get($fullJsonPath), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decodedRows) || count($decodedRows) !== (int) ($meta['row_count'] ?? -1)) {
                $this->error("Jumlah baris tabel {$tableName} tidak sesuai dengan metadata manifest.");

                return self::FAILURE;
            }

            $tables[$tableName] = $decodedRows;
        }

        foreach ($manifestFiles as $relativePath => $meta) {
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

        // Uji dekripsi identitas terenkripsi pada paket tanpa mencetak nomor mentah ke layar/log.
        foreach (['ktp_number', 'nik_ktp', 'sim_number'] as $identityColumn) {
            $cipher = $customer[$identityColumn] ?? null;
            if (is_string($cipher) && $cipher !== '' && ! $this->canDecryptValue($cipher)) {
                $this->error("Kolom terenkripsi {$identityColumn} gagal didekripsi dengan APP_KEY aktif.");

                return self::FAILURE;
            }
        }

        // Kumpulkan seluruh referensi berkas privat transaksi: identitas, bukti bayar utama (proof_path),
        // bukti bayar perpanjangan (payment_proof), foto inspeksi (condition_photo), kerusakan, dan tagihan tambahan.
        $referencedFiles = array_filter([
            $customer['ktp_image'] ?? null,
            $customer['sim_image'] ?? null,
            $targetRental['damage_evidence_path'] ?? null,
            $targetRental['additional_charge_proof'] ?? null,
        ]);

        foreach (($tables['rental_payments'] ?? []) as $payment) {
            if (($payment['rental_id'] ?? null) === $targetRental['id'] && ! empty($payment['proof_path'])) {
                $referencedFiles[] = $payment['proof_path'];
            }
        }

        foreach (($tables['rental_extensions'] ?? []) as $extension) {
            if (($extension['rental_id'] ?? null) === $targetRental['id'] && ! empty($extension['payment_proof'])) {
                $referencedFiles[] = $extension['payment_proof'];
            }
        }

        foreach (($tables['inspections'] ?? []) as $inspection) {
            if (($inspection['rental_id'] ?? null) === $targetRental['id'] && ! empty($inspection['condition_photo'])) {
                $referencedFiles[] = $inspection['condition_photo'];
            }
        }

        $uniqueReferencedFiles = array_values(array_unique($referencedFiles));
        foreach ($uniqueReferencedFiles as $relFile) {
            $normalized = str_replace('\\', '/', (string) $relFile);
            $expectedBackupFile = $backupDir.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);

            // Restore menyalin entri yang ada dalam manifest. Periksa kedua sumber
            // sebelum menulis database agar berkas yang lolos pemeriksaan ikut disalin.
            if (! array_key_exists($normalized, $manifestFiles) || ! File::exists($expectedBackupFile)) {
                $this->error("Berkas privat transaksi {$targetRental['rental_code']} ({$normalized}) tidak tersedia dalam paket cadangan.");

                return self::FAILURE;
            }
        }

        $targetDb = trim((string) $this->option('target-db'));
        $targetStorage = trim((string) $this->option('target-storage'));

        // Pemulihan nyata (--target-db atau --target-storage) hanya boleh berjalan pada lingkungan testing
        // agar database utama, database proyek lain, maupun berkas aktif tidak pernah tersentuh.
        if (($targetDb !== '' || $targetStorage !== '') && ! app()->environment('testing')) {
            $this->error('Pemulihan nyata (--target-db dan --target-storage) hanya diizinkan pada lingkungan testing (APP_ENV=testing).');

            return self::FAILURE;
        }

        if ($targetDb !== '') {
            if (! $this->performIsolatedDatabaseRestore($targetDb, $tables, $targetRental['rental_code'])) {
                return self::FAILURE;
            }
        }

        if ($targetStorage !== '') {
            if (! $this->performIsolatedStorageRestore($targetStorage, $backupDir, $manifestFiles, $uniqueReferencedFiles)) {
                return self::FAILURE;
            }
        }

        $elapsedMs = (int) round((hrtime(true) - $startHr) / 1_000_000);
        $this->info('Uji pemulihan terisolasi BERHASIL untuk transaksi '.$targetRental['rental_code']
            .' (berkas diperiksa: '.count($uniqueReferencedFiles)
            .', waktu pemulihan: '.$elapsedMs.' ms dari batas target SKPL 4 jam).');

        return self::SUCCESS;
    }

    /**
     * Impor seluruh tabel cadangan ke database PostgreSQL kosong terisolasi, setel ulang sequence, dan verifikasi hasilnya.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function performIsolatedDatabaseRestore(string $targetDb, array $tables, string $targetRentalCode): bool
    {
        // Hanya izinkan nama database dengan awalan khusus latihan restore testing (driveease_restore_*).
        // Pola ini otomatis menolak database aplikasi lain maupun nama sembarang di server PostgreSQL.
        if (preg_match('/^driveease_restore_[a-z0-9_]+$/', $targetDb) !== 1) {
            $this->error('Nama target-db tidak valid; wajib diawali driveease_restore_ serta hanya berisi huruf kecil, angka, dan garis bawah.');

            return false;
        }

        $activeDefault = (string) config('database.default');
        $activeDbName = strtolower((string) config("database.connections.{$activeDefault}.database"));
        $configuredPgsqlDb = strtolower((string) config('database.connections.pgsql.database'));

        if (in_array(strtolower($targetDb), self::FORBIDDEN_TARGET_DBS, true)
            || strtolower($targetDb) === $activeDbName
            || strtolower($targetDb) === $configuredPgsqlDb) {
            $this->error("Database {$targetDb} dilindungi dan tidak boleh ditimpa oleh latihan pemulihan.");

            return false;
        }

        $basePgsql = config('database.connections.pgsql');
        if (! is_array($basePgsql)) {
            $this->error('Konfigurasi koneksi pgsql tidak ditemukan.');

            return false;
        }

        $previousDefault = DB::getDefaultConnection();

        try {
            // Parser Laravel mengubah DB_URL menjadi opsi koneksi. Terapkan nama
            // target sesudah parsing agar URL sumber tidak mengganti database tujuan.
            $basePgsql = (new ConfigurationUrlParser)->parseConfiguration($basePgsql);
            if (strtolower((string) ($basePgsql['database'] ?? '')) === $targetDb) {
                $this->error('Target pemulihan harus berbeda dari database pada konfigurasi PostgreSQL aktif.');

                return false;
            }

            config([
                'database.connections.restore_verify' => array_merge($basePgsql, [
                    'database' => $targetDb,
                ]),
            ]);
            DB::purge('restore_verify');
            $restoreConn = DB::connection('restore_verify');
            if ($restoreConn->getDriverName() !== 'pgsql') {
                $this->error('Target restore database harus menggunakan driver PostgreSQL.');

                return false;
            }
            // Argumen ketiga false memakai koneksi tulis yang juga dipakai migration.
            // PostgreSQL mengembalikan nama database yang benar-benar menerima query.
            $connectedDb = $restoreConn->selectOne('SELECT current_database() AS name', [], false)->name;
            if ($connectedDb !== $targetDb) {
                $this->error('Koneksi PostgreSQL tidak mengarah ke database target pemulihan.');

                return false;
            }

            // Periksa skema public sebelum migration: apabila sudah ada satu tabel saja, tolak proses
            // agar latihan restore tidak pernah menghapus atau menimpa data pada database yang sudah terisi.
            $existingTableCount = (int) ($restoreConn->selectOne(
                "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'",
                [],
                false,
            )->total ?? 0);

            if ($existingTableCount > 0) {
                $this->error("Database target {$targetDb} tidak kosong ({$existingTableCount} tabel ditemukan). Pemulihan hanya diizinkan pada database PostgreSQL kosong.");

                return false;
            }

            // Alihkan default connection sementara selama migrate dan impor agar DB::statement()
            // di file migration terdahulu ikut mengarah ke restore_verify, bukan ke database aktif.
            DB::setDefaultConnection('restore_verify');
            config(['database.default' => 'restore_verify']);

            // Jalankan migrate (bukan migrate:fresh) supaya perintah tidak pernah mengeksekusi DROP TABLE/db:wipe.
            $migrateStatus = Artisan::call('migrate', [
                '--database' => 'restore_verify',
                '--force' => true,
            ]);

            if ($migrateStatus !== 0) {
                $this->error('Gagal menjalankan migration pada database pemulihan terisolasi: '.trim(Artisan::output()));

                return false;
            }

            $restoreConn->transaction(function () use ($restoreConn, $tables): void {
                foreach (self::RESTORE_ORDER as $table) {
                    $rows = $tables[$table] ?? [];
                    foreach (array_chunk($rows, 200) as $chunk) {
                        $restoreConn->table($table)->insert($chunk);
                    }

                    // Setel ulang sequence PostgreSQL agar penyisipan data baru setelah restore tidak bentrok primary key.
                    $hasRows = $restoreConn->table($table)->exists();
                    $maxId = $hasRows ? (int) $restoreConn->table($table)->max('id') : 1;
                    $restoreConn->statement(
                        "SELECT setval(pg_get_serial_sequence(?, 'id'), ?, ?)",
                        [$table, max(1, $maxId), $hasRows]
                    );
                }
            });

            foreach (self::RESTORE_ORDER as $table) {
                $expectedCount = count($tables[$table] ?? []);
                $actualCount = $restoreConn->table($table)->count();
                if ($actualCount !== $expectedCount) {
                    $this->error("Jumlah baris hasil restore pada tabel {$table} ({$actualCount}) tidak sama dengan cadangan ({$expectedCount}).");

                    return false;
                }
            }

            // Pastikan transaksi target dapat dibaca kembali langsung dari PostgreSQL hasil restore beserta dekripsi identitasnya.
            $restoredRental = $restoreConn->table('rentals')->where('rental_code', $targetRentalCode)->first();
            $restoredUser = $restoredRental ? $restoreConn->table('users')->where('id', $restoredRental->user_id)->first() : null;
            if (! $restoredRental || ! $restoredUser) {
                $this->error("Transaksi {$targetRentalCode} gagal ditemukan pada database hasil restore.");

                return false;
            }

            foreach (['ktp_number', 'nik_ktp', 'sim_number'] as $identityColumn) {
                $cipher = $restoredUser->{$identityColumn} ?? null;
                if (is_string($cipher) && $cipher !== '' && ! $this->canDecryptValue($cipher)) {
                    $this->error("Identitas {$identityColumn} pada database hasil restore gagal didekripsi.");

                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            $this->error('Pemulihan ke database terisolasi gagal: '.$exception->getMessage());

            return false;
        } finally {
            DB::setDefaultConnection($previousDefault);
            config(['database.default' => $previousDefault]);
            DB::purge('restore_verify');
        }
    }

    /**
     * Salin dan verifikasi ulang SHA-256 berkas privat ke subdirektori kosong di bawah storage/framework/testing.
     *
     * @param  array<string, array<string, mixed>>  $manifestFiles
     * @param  list<string>  $referencedFiles
     */
    private function performIsolatedStorageRestore(
        string $targetStorage,
        string $backupDir,
        array $manifestFiles,
        array $referencedFiles
    ): bool {
        $testingRoot = $this->normalizeAbsolutePath(storage_path('framework/testing'));
        File::ensureDirectoryExists($testingRoot);
        $realTestingRoot = realpath($testingRoot);
        if ($realTestingRoot === false) {
            $this->error('Direktori sandbox testing storage/framework/testing tidak tersedia.');

            return false;
        }

        $resolvedTarget = $this->normalizeAbsolutePath($targetStorage);
        $resolvedBackupDir = $this->normalizeAbsolutePath($backupDir);

        $toSlashLower = static fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        $candidateLower = $toSlashLower($resolvedTarget);
        $testingRootLower = $toSlashLower($testingRoot);
        $backupDirLower = $toSlashLower($resolvedBackupDir);
        $realTestingRootLower = $toSlashLower($realTestingRoot);

        // Wajibkan target berada di subdirektori khusus di dalam storage/framework/testing
        // dan tolak root testing itu sendiri agar data lain di luar sandbox tidak pernah tersentuh.
        if (! str_starts_with($candidateLower, $testingRootLower.'/')) {
            $this->error('Direktori target-storage wajib berupa subdirektori terisolasi di dalam storage/framework/testing.');

            return false;
        }

        // Cegah tumpang tindih dengan paket sumber cadangan maupun area publik/storage aktif aplikasi.
        if ($candidateLower === $backupDirLower
            || str_starts_with($candidateLower, $backupDirLower.'/')
            || str_starts_with($backupDirLower, $candidateLower.'/')) {
            $this->error('Direktori target-storage tidak boleh sama dengan atau bertumpang tindih dengan direktori sumber cadangan.');

            return false;
        }

        $forbiddenRoots = [
            $toSlashLower(public_path()),
            $toSlashLower(storage_path('app/public')),
            $toSlashLower(Storage::disk('local')->path('')),
            $toSlashLower(storage_path('app/backups')),
        ];

        foreach ($forbiddenRoots as $root) {
            if ($candidateLower === $root || str_starts_with($candidateLower, $root.'/')) {
                $this->error('Direktori target-storage harus terisolasi dari direktori publik maupun storage aktif aplikasi.');

                return false;
            }
        }

        // Tolak symlink maupun berkas biasa serta pastikan seluruh ancestor yang sudah ada tidak keluar via symlink.
        if (is_link($resolvedTarget) || (file_exists($resolvedTarget) && ! is_dir($resolvedTarget))) {
            $this->error('Target-storage bukan direktori valid yang aman untuk latihan pemulihan.');

            return false;
        }

        $cursor = $resolvedTarget;
        while ($cursor !== '' && ! file_exists($cursor)) {
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $cursor = $parent;
        }

        if ($cursor === '' || is_link($cursor)) {
            $this->error('Target-storage mengandung jalur tautan simbolik (symlink) yang ditolak.');

            return false;
        }

        $realAncestor = realpath($cursor);
        if ($realAncestor === false) {
            $this->error('Jalur induk target-storage tidak dapat diverifikasi secara aman.');

            return false;
        }
        $realAncestorLower = $toSlashLower($realAncestor);
        if ($realAncestorLower !== $realTestingRootLower && ! str_starts_with($realAncestorLower, $realTestingRootLower.'/')) {
            $this->error('Jalur fisik target-storage keluar dari sandbox storage/framework/testing.');

            return false;
        }

        // Jangan pernah menghapus isi direktori target; wajibkan target belum ada atau benar-benar kosong.
        if (is_dir($resolvedTarget)) {
            $realTarget = realpath($resolvedTarget);
            if ($realTarget === false || ! str_starts_with($toSlashLower($realTarget), $realTestingRootLower.'/')) {
                $this->error('Jalur fisik direktori target-storage berada di luar sandbox testing.');

                return false;
            }

            $entries = array_diff(scandir($resolvedTarget) ?: [], ['.', '..']);
            if ($entries !== []) {
                $this->error('Direktori target-storage harus belum ada atau benar-benar kosong agar tidak menimpa atau menghapus data yang sudah ada.');

                return false;
            }
        }

        try {
            File::ensureDirectoryExists($resolvedTarget);

            foreach ($manifestFiles as $relativePath => $meta) {
                $sourcePath = $backupDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) ($meta['backup_file'] ?? ''));
                $destPath = $resolvedTarget.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);
                File::ensureDirectoryExists(dirname($destPath));
                File::copy($sourcePath, $destPath);

                $restoredHash = hash_file('sha256', $destPath);
                if (! hash_equals((string) ($meta['sha256'] ?? ''), (string) $restoredHash)) {
                    $this->error("Checksum berkas hasil restore {$relativePath} tidak cocok.");

                    return false;
                }
            }

            foreach ($referencedFiles as $relFile) {
                $normalized = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $relFile));
                if (! File::exists($resolvedTarget.DIRECTORY_SEPARATOR.$normalized)) {
                    $this->error("Berkas transaksi {$relFile} tidak ditemukan di target-storage hasil pemulihan.");

                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            $this->error('Pemulihan berkas privat ke storage terisolasi gagal: '.$exception->getMessage());

            return false;
        }
    }

    /**
     * Normalisasi path absolut secara aman sebelum memvalidasi batas direktori target-storage.
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
