<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalExtensionService;
use App\Services\RentalOperationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class BackupRestoreDrillTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Restore Nyata',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Pemulihan No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji latihan restore terisolasi.',
            'bank_name' => 'Bank Restore DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Restore',
        ], ['id']);
    }

    public function test_backup_protects_against_public_exposure_and_real_isolated_restore_reopens_transaction(): void
    {
        $originalLocalDisk = Storage::disk('local');
        $fakeLocal = Storage::fake('local');
        Storage::set('local', $fakeLocal);

        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        Storage::disk('local')->put('identities/ktp-drill.jpg', 'ktp-drill-binary');
        Storage::disk('local')->put('identities/sim-drill.jpg', 'sim-drill-binary');
        $customer->forceFill([
            'ktp_image' => 'identities/ktp-drill.jpg',
            'sim_image' => 'identities/sim-drill.jpg',
        ])->saveOrFail();

        $car = $this->createBookableCar(500000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(4)->startOfHour();
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);

        // 1. Pembayaran utama lunas + arus kas.
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('drill-main.jpg'),
        ])->assertRedirect();
        $payment = $rental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        // 2. Perpanjangan sewa + bukti bayar perpanjangan (payment_proof) + arus kas.
        $extensionService = app(RentalExtensionService::class);
        $extension = $extensionService->requestExtension($rental->fresh(), $customer, 1, 'Tambah 1 hari');
        $extension = $extensionService->approveExtension($extension, $admin);
        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $extension), [
            'payment_proof' => UploadedFile::fake()->image('drill-ext.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => 500000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        // 3. Inspeksi pengambilan & pengembalian + foto + biaya tambahan kerusakan + bukti bayar tambahan.
        app(RentalOperationService::class)->confirmPickup(
            $rental->fresh(),
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
                'odometer_km' => 10000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('drill-pickup.jpg')
        );

        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $rental->fresh()->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 10150,
            'fuel_level' => 'half',
            'has_damage' => 1,
            'damage_description' => 'Baret bemper belakang untuk uji bukti kerusakan.',
            'condition_photo' => UploadedFile::fake()->image('drill-return.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 50000,
            'fuel_fee_reason' => 'BBM kurang.',
            'damage_fee_amount' => 150000,
            'damage_fee_reason' => 'Poles bemper.',
            'damage_evidence' => UploadedFile::fake()->image('drill-damage.jpg'),
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('drill-add-pay.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.approve', $rental), [
            'verified_amount' => 200000,
            'payment_reference' => 'DRILL-ADD-200K',
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $rental->refresh()->load(['pickupInspection', 'returnInspection']);

        $backupDir = storage_path('framework/testing/backup_drill_'.uniqid());
        $restoredStorageDir = storage_path('framework/testing/restored_private_'.uniqid());
        $restoreDbName = 'driveease_restore_test';
        $postgresPdo = $this->connectPostgresMaintenance();

        try {
            // Perlindungan publik: tolak --output ke direktori publik dan pastikan URL /storage/backups tidak dapat diakses.
            $this->artisan('driveease:backup', ['--output' => public_path('backups_forbidden')])
                ->assertExitCode(1);
            $this->artisan('driveease:backup', ['--output' => storage_path('app/public/backups_forbidden')])
                ->assertExitCode(1);
            // Pastikan jalur HTTP cadangan tidak dapat diakses publik (Laravel storage.local menolak 403 tanpa tanda tangan dan /backups 404).
            $this->assertContains($this->get('/storage/backups/manifest.json')->status(), [403, 404]);
            $this->get('/backups/manifest.json')->assertNotFound();

            // Buat paket cadangan lengkap.
            $this->artisan('driveease:backup', ['--output' => $backupDir])
                ->assertExitCode(0);

            $this->assertFileExists($backupDir.DIRECTORY_SEPARATOR.'.htaccess');
            $this->assertStringContainsString('Require all denied', (string) File::get($backupDir.DIRECTORY_SEPARATOR.'.htaccess'));
            $this->assertFileExists($backupDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'rental_cash_flows.json');

            // Siapkan database PostgreSQL terisolasi driveease_restore_test (perintah juga melarang penimpaan driveease_db).
            $this->artisan('driveease:verify-restore', [
                'path' => $backupDir,
                '--rental-code' => $rental->rental_code,
                '--target-db' => 'driveease_db',
            ])->assertExitCode(1);

            $this->recreateIsolatedDatabase($postgresPdo, $restoreDbName);

            // Jalankan restore nyata ke driveease_restore_test dan direktori storage privat terisolasi.
            $this->artisan('driveease:verify-restore', [
                'path' => $backupDir,
                '--rental-code' => $rental->rental_code,
                '--target-db' => $restoreDbName,
                '--target-storage' => $restoredStorageDir,
            ])->assertExitCode(0);

            // Arahkan koneksi default Laravel dan disk privat sementara ke hasil pemulihan untuk membuktikan aplikasi
            // benar-benar dapat membuka satu transaksi hasil restore beserta seluruh berkas privat & dekripsi identitasnya.
            $basePgsql = config('database.connections.pgsql');
            config([
                'database.connections.restore_app' => array_merge($basePgsql, ['database' => $restoreDbName]),
                'database.default' => 'restore_app',
            ]);
            DB::setDefaultConnection('restore_app');
            DB::purge('restore_app');

            Storage::set('local', Storage::build([
                'driver' => 'local',
                'root' => $restoredStorageDir,
            ]));

            // Uji sequence PostgreSQL: penyisipan baris baru pada DB hasil restore harus sukses tanpa bentrok primary key.
            $newTypeAfterRestore = CarType::create([
                'name' => 'Tipe Pasca Restore '.uniqid(),
                'description' => 'Memastikan sequence PostgreSQL berjalan setelah setval.',
            ]);
            $this->assertGreaterThan(0, $newTypeAfterRestore->id);

            $restoredAdmin = User::findOrFail($admin->id);
            $restoredCustomer = User::findOrFail($customer->id);
            $restoredRental = Rental::where('rental_code', $rental->rental_code)->firstOrFail();

            $this->assertSame('3174012345678901', $restoredCustomer->ktp_number);
            $this->assertSame('12345678901234', $restoredCustomer->sim_number);

            // Pelanggan pemilik membuka kembali transaksi & seluruh berkas privat hasil pemulihan via HTTP.
            $this->actingAs($restoredCustomer)->get(route('customer.bookings.show', $restoredRental))
                ->assertOk()
                ->assertSee($rental->rental_code)
                ->assertSee('DRILL-ADD-200K');

            foreach ([
                route('documents.identity.show', ['user' => $restoredCustomer, 'type' => 'ktp']),
                route('documents.identity.show', ['user' => $restoredCustomer, 'type' => 'sim']),
                route('documents.payment.show', $payment->id),
                route('documents.extension-payment.show', $extension->id),
                route('documents.inspection-photo.show', $rental->pickupInspection->id),
                route('documents.inspection-photo.show', $rental->returnInspection->id),
                route('documents.damage-evidence.show', $restoredRental->id),
                route('documents.additional-charge-proof.show', $restoredRental->id),
            ] as $documentUrl) {
                $docResponse = $this->actingAs($restoredCustomer)->get($documentUrl)->assertOk();
                $this->assertStringContainsString('no-store', (string) $docResponse->headers->get('Cache-Control'));
            }

            // Admin membuka detail transaksi & laporan kas dari hasil pemulihan.
            $this->actingAs($restoredAdmin)->get(route('admin.bookings.show', $restoredRental))
                ->assertOk()
                ->assertSee('Buku Arus Kas Transaksi')
                ->assertSee('DRILL-ADD-200K');
            $this->actingAs($restoredAdmin)->get(route('admin.reports.index'))
                ->assertOk()
                ->assertSee($rental->rental_code);
        } finally {
            DB::setDefaultConnection('pgsql');
            config(['database.default' => 'pgsql']);
            DB::purge('restore_app');
            Storage::set('local', $originalLocalDisk);

            $this->dropIsolatedDatabase($postgresPdo, $restoreDbName);
            File::deleteDirectory($backupDir);
            File::deleteDirectory($restoredStorageDir);
            File::deleteDirectory(public_path('backups_forbidden'));
            File::deleteDirectory(storage_path('app/public/backups_forbidden'));
        }
    }

    public function test_verify_restore_rejects_incomplete_manifest_even_when_files_exist(): void
    {
        $customer = $this->createVerifiedCustomer();
        $identityPath = 'identities/ktp-manifest.jpg';
        Storage::disk('local')->put($identityPath, 'private-test-image');
        $customer->forceFill(['ktp_image' => $identityPath])->saveOrFail();
        $car = $this->createBookableCar(450000);
        $pickup = CarbonImmutable::now('Asia/Jakarta')->addHours(4)->startOfHour();
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickup, 1, true);
        $backupDir = storage_path('framework/testing/backup_manifest_'.uniqid());
        $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';

        try {
            $this->artisan('driveease:backup', ['--output' => $backupDir])->assertExitCode(0);
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            // Hapus entri satu per satu dari manifest. Berkas asli sengaja tetap ada
            // agar uji membedakan inventaris yang belum lengkap dari berkas yang hilang.
            foreach (['tables' => 'rental_cash_flows', 'files' => $identityPath] as $section => $entry) {
                $incomplete = $manifest;
                unset($incomplete[$section][$entry]);
                File::put($manifestPath, json_encode($incomplete, JSON_THROW_ON_ERROR));

                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                ])->assertExitCode(1);
            }
        } finally {
            File::deleteDirectory($backupDir);
        }
    }

    /**
     * Memastikan target restore nyata tidak dapat menghapus atau menimpa data di luar lingkungan testing yang kosong.
     */
    public function test_verify_restore_blocks_non_testing_environment_and_prevents_deleting_existing_database_or_storage_data(): void
    {
        $originalLocalDisk = Storage::disk('local');
        Storage::set('local', Storage::fake('local'));

        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        Storage::disk('local')->put('identities/ktp-guard.jpg', 'ktp-guard-binary');
        $customer->forceFill(['ktp_image' => 'identities/ktp-guard.jpg'])->saveOrFail();

        $car = $this->createBookableCar(450000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(6)->startOfHour();
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('guard-main.jpg'),
        ])->assertRedirect();
        $payment = $rental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $suffix = uniqid();
        $backupDir = storage_path('framework/testing/backup_guard_'.$suffix);
        $outsideDir = storage_path('framework/cache/restore_guard_outside_'.$suffix);
        $nonEmptyTargetDir = storage_path('framework/testing/restore_nonempty_'.$suffix);
        $nonTestingTargetDir = storage_path('framework/testing/restore_nontest_'.$suffix);
        $nonEmptyDbName = 'driveease_restore_nonempty_'.strtolower(substr(preg_replace('/[^a-z0-9]/i', '', $suffix) ?: 'x', 0, 8));
        $postgresPdo = $this->connectPostgresMaintenance();
        $originalEnv = $this->app->environment();

        try {
            $this->artisan('driveease:backup', ['--output' => $backupDir])->assertExitCode(0);

            // 1. Pada environment non-testing (production dan local), pemulihan ke --target-db maupun --target-storage wajib ditolak,
            //    sedangkan verifikasi paket read-only tanpa opsi target tetap diperbolehkan.
            foreach (['production', 'local'] as $nonTestingEnv) {
                $this->app->detectEnvironment(fn (): string => $nonTestingEnv);

                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                    '--target-db' => 'driveease_restore_blocked',
                ])->assertExitCode(1);

                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                    '--target-storage' => $nonTestingTargetDir,
                ])->assertExitCode(1);

                $this->assertDirectoryDoesNotExist($nonTestingTargetDir);

                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                ])->assertExitCode(0);
            }

            $this->app->detectEnvironment(fn (): string => $originalEnv);

            // 2. Tolak target-db yang tidak berawalan driveease_restore_ (termasuk database aplikasi lain).
            foreach (['driveease_db', 'driveease_test', 'other_app_db'] as $forbiddenDb) {
                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                    '--target-db' => $forbiddenDb,
                ])->assertExitCode(1);
            }

            // 3. Buat database berawalan driveease_restore_ tetapi sudah memiliki tabel dan data: harus ditolak dan datanya utuh.
            $this->recreateIsolatedDatabase($postgresPdo, $nonEmptyDbName);
            $basePgsql = config('database.connections.pgsql');
            $nonEmptyPdo = new PDO(
                "pgsql:host={$basePgsql['host']};port={$basePgsql['port']};dbname={$nonEmptyDbName}",
                (string) $basePgsql['username'],
                (string) $basePgsql['password']
            );
            $nonEmptyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $nonEmptyPdo->exec('CREATE TABLE sentinel_keep (id INT PRIMARY KEY, marker TEXT NOT NULL)');
            $nonEmptyPdo->exec("INSERT INTO sentinel_keep (id, marker) VALUES (1, 'do-not-delete')");

            $this->artisan('driveease:verify-restore', [
                'path' => $backupDir,
                '--rental-code' => $rental->rental_code,
                '--target-db' => $nonEmptyDbName,
            ])->assertExitCode(1);

            $marker = $nonEmptyPdo->query('SELECT marker FROM sentinel_keep WHERE id = 1')->fetchColumn();
            $this->assertSame('do-not-delete', $marker);
            $nonEmptyPdo = null;

            // 4. Tolak target-storage di luar sandbox testing, root testing itu sendiri, traversal .., tumpang tindih backupDir,
            //    maupun subdirektori sandbox yang sudah berisi berkas; seluruh berkas yang ada tidak boleh terhapus.
            File::ensureDirectoryExists($outsideDir);
            File::put($outsideDir.DIRECTORY_SEPARATOR.'keep.txt', 'outside-safe');
            File::ensureDirectoryExists($nonEmptyTargetDir);
            File::put($nonEmptyTargetDir.DIRECTORY_SEPARATOR.'existing.txt', 'sandbox-nonempty-safe');

            $traversalPath = storage_path('framework/testing/../cache/restore_guard_outside_'.$suffix);

            foreach ([$outsideDir, storage_path('framework/testing'), $traversalPath, $backupDir, $nonEmptyTargetDir] as $unsafeStorage) {
                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                    '--target-storage' => $unsafeStorage,
                ])->assertExitCode(1);
            }

            $this->assertSame('outside-safe', File::get($outsideDir.DIRECTORY_SEPARATOR.'keep.txt'));
            $this->assertSame('sandbox-nonempty-safe', File::get($nonEmptyTargetDir.DIRECTORY_SEPARATOR.'existing.txt'));
            $this->assertFileExists($backupDir.DIRECTORY_SEPARATOR.'manifest.json');
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnv);
            Storage::set('local', $originalLocalDisk);
            $this->dropIsolatedDatabase($postgresPdo, $nonEmptyDbName);
            File::deleteDirectory($backupDir);
            File::deleteDirectory($outsideDir);
            File::deleteDirectory($nonEmptyTargetDir);
            File::deleteDirectory($nonTestingTargetDir);
        }
    }

    private function connectPostgresMaintenance(): PDO
    {
        $host = (string) config('database.connections.pgsql.host', '127.0.0.1');
        $port = (string) config('database.connections.pgsql.port', '5432');
        $username = (string) config('database.connections.pgsql.username', 'postgres');
        $password = (string) config('database.connections.pgsql.password', '');

        $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function recreateIsolatedDatabase(PDO $postgresPdo, string $dbName): void
    {
        $this->dropIsolatedDatabase($postgresPdo, $dbName);
        $postgresPdo->exec("CREATE DATABASE \"{$dbName}\"");
    }

    private function dropIsolatedDatabase(PDO $postgresPdo, string $dbName): void
    {
        $stmt = $postgresPdo->prepare('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :db AND pid <> pg_backend_pid()');
        $stmt->execute(['db' => $dbName]);
        $postgresPdo->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Drill '.uniqid(),
            'email' => $roleName.'_drill_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Drill '.uniqid(),
            'email' => 'cust_drill_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function createBookableCar(int $pricePerDay): Car
    {
        $carType = CarType::firstOrCreate(['name' => 'SUV Drill'], ['description' => 'Tipe uji pemulihan']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Toyota Fortuner Drill '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' DR',
            'brand' => 'Toyota',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 7,
            'price_per_day' => $pricePerDay,
            'status' => 'available',
            'readiness_status' => Car::READINESS_READY,
        ]);
    }
}
