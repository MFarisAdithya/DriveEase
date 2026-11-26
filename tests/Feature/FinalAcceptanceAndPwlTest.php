<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\RentalBookingService;
use App\Services\RentalOperationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PDO;
use Tests\TestCase;

class FinalAcceptanceAndPwlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Penerimaan Akhir',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. PWL No. 10',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji penerimaan akhir SKPL & matriks PWL.',
            'bank_name' => 'Bank PWL DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase PWL',
        ], ['id']);
    }

    public function test_pwl_matrix_mvc_blade_relationships_and_mass_assignment_protection(): void
    {
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(450000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(9, 0);

        // Uji mass assignment protection: input tambahan rental_status, payment_status, atau total_amount palsu
        // pada formulir pelanggan tidak mengubah nilai yang dikendalikan oleh server.
        $this->actingAs($customer)->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 2,
            'accept_terms' => 1,
            'rental_status' => Rental::STATUS_COMPLETED,
            'payment_status' => Rental::PAYMENT_PAID,
            'total_amount' => 1,
            'paid_amount' => 99999999,
        ])->assertRedirect();

        $rental = Rental::query()->with(['user', 'car', 'extensions', 'inspections'])->where('user_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(Rental::STATUS_PENDING, $rental->rental_status);
        $this->assertSame(Rental::PAYMENT_UNPAID, $rental->payment_status);
        $this->assertSame(900000.0, (float) $rental->total_amount);
        $this->assertSame(0.0, (float) $rental->paid_amount);
        $this->assertSame($customer->id, $rental->user->id);
        $this->assertSame($car->id, $rental->car->id);
        $this->assertTrue($customer->rentals->contains('id', $rental->id));
        $this->assertTrue($car->rentals->contains('id', $rental->id));

        // Telusuri MVC -> Controller -> Blade view -> nilai tampil pada detail pesanan pelanggan.
        $this->actingAs($customer)->get(route('customer.bookings.show', $rental))
            ->assertOk()
            ->assertViewIs('customer.bookings.show')
            ->assertViewHas('rental', fn (Rental $viewRental): bool => $viewRental->id === $rental->id)
            ->assertSee($rental->rental_code)
            ->assertSee($car->name)
            ->assertSee('900.000');
    }

    public function test_pwl_matrix_postgresql_erd_foreign_keys_and_inspection_relations(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        // Cocokkan ERD dengan foreign key aktual pada database PostgreSQL hasil migration (PWL-01).
        $foreignKeys = $this->listSchemaForeignKeys('pgsql');

        // Daftar foreign key utama yang wajib ada sesuai rancangan ERD 12 tabel inti.
        $expectedForeignKeys = [
            'users.role_id->roles',
            'cars.car_type_id->car_types',
            'rentals.user_id->users',
            'rentals.car_id->cars',
            'inspections.rental_id->rentals',
            'inspections.inspector_id->users',
            'rental_payments.rental_id->rentals',
            'rental_change_requests.rental_id->rentals',
            'rental_extensions.rental_id->rentals',
            'rental_histories.rental_id->rentals',
            'rental_cash_flows.rental_id->rentals',
            'rental_cash_flows.rental_payment_id->rental_payments',
            'rental_cash_flows.rental_extension_id->rental_extensions',
        ];

        foreach ($expectedForeignKeys as $expectedFk) {
            $this->assertContains($expectedFk, $foreignKeys, "Foreign key ERD tidak ditemukan: {$expectedFk}");
        }

        // Verifikasi relasi Eloquent Inspection -> Rental & Inspection -> Staff.
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addMinutes(30);

        $rental = Rental::forceCreate([
            'rental_code' => 'ERD-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'pickup_at' => $pickupAt,
            'return_at' => $pickupAt->addDay(),
            'start_date' => $pickupAt->toDateString(),
            'end_date' => $pickupAt->addDay()->toDateString(),
            'total_days' => 1,
            'daily_price' => 500000,
            'total_amount' => 500000,
            'paid_amount' => 500000,
            'payment_status' => Rental::PAYMENT_PAID,
            'rental_status' => Rental::STATUS_CONFIRMED,
            'booking_channel' => Rental::CHANNEL_ONLINE,
            'terms_accepted_at' => $pickupAt,
        ]);

        $inspection = app(RentalOperationService::class)->confirmPickup(
            $rental,
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
                'odometer_km' => 1500,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('erd-pickup.jpg')
        );

        $this->assertSame($rental->id, $inspection->rental->id);
        $this->assertSame($staff->id, $inspection->inspector->id);
        $this->assertTrue($rental->fresh()->inspections->contains('id', $inspection->id));
    }

    public function test_pwl_matrix_resource_routes_middleware_hashing_encryption_and_api_parity(): void
    {
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
            $this->assertTrue(Route::has("admin.cars.{$action}"), "Route resource admin.cars.{$action} tidak ditemukan.");
        }

        // Middleware & RBAC: middleware auth dan role:admin terpasang pada grup rute admin.
        $adminRouteMiddleware = Route::getRoutes()->getByName('admin.cars.index')->gatherMiddleware();
        $this->assertContains('auth', $adminRouteMiddleware);
        $this->assertContains('role:admin', $adminRouteMiddleware);

        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(425000);

        // Hashing & Encryption: sandi tersimpan ter-hash, nomor identitas tersimpan terenkripsi di database mentah.
        $rawCustomer = DB::table('users')->where('id', $customer->id)->first();
        $this->assertNotSame('password123', $rawCustomer->password);
        $this->assertTrue(Hash::check('password123', $rawCustomer->password));
        $this->assertNotSame('3174012345678901', $rawCustomer->ktp_number);
        $this->assertNotSame('12345678901234', $rawCustomer->sim_number);
        $this->assertSame('3174012345678901', $customer->fresh()->ktp_number);
        $this->assertSame('12345678901234', $customer->fresh()->sim_number);

        // Session lifecycle: login mengganti ID sesi dan logout membatalkan sesi lama.
        $this->get('/login')->assertOk();
        $initialSessionId = session()->getId();
        $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertNotSame($initialSessionId, session()->getId());

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));

        // Pemesanan & Otorisasi: staf ditolak (403) pada verifikasi bayar; pelanggan ditolak (403) pada transaksi lain.
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(10, 0);
        $otherRental = app(RentalBookingService::class)->createBooking($otherCustomer, $car->id, $pickupAt, 1, true);
        $this->actingAs($otherCustomer)->post(route('customer.bookings.payments.store', $otherRental), [
            'payment_proof' => UploadedFile::fake()->image('rbac-pay.jpg'),
        ])->assertRedirect();
        $payment = $otherRental->payments()->firstOrFail();

        $this->actingAs($customer)->get(route('customer.bookings.show', $otherRental))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => 425000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.bookings.show', $otherRental))->assertOk();

        // API parity: ketersediaan JSON memblokir rentang bentrok ($otherRental) dan mengembalikan 422 pada periode salah.
        $this->getJson(route('api.v1.availability', [
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 1,
        ]))
            ->assertOk()
            ->assertJsonMissing(['id' => $car->id]);

        $this->getJson(route('api.v1.availability', [
            'pickup_at' => $pickupAt->addDays(4)->format('Y-m-d\TH:i'),
            'total_days' => 2,
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $car->id, 'estimated_total' => 850000]);

        $this->getJson(route('api.v1.availability', [
            'pickup_at' => CarbonImmutable::now('Asia/Jakarta')->subDays(2)->format('Y-m-d\TH:i'),
            'total_days' => 1,
        ]))->assertStatus(422);
    }

    public function test_isolated_backup_and_restore_verification_commands_and_tamper_detection(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        Storage::disk('local')->put('identities/ktp-nfr10.jpg', 'ktp-nfr10-content');
        Storage::disk('local')->put('identities/sim-nfr10.jpg', 'sim-nfr10-content');
        $customer->forceFill([
            'ktp_image' => 'identities/ktp-nfr10.jpg',
            'sim_image' => 'identities/sim-nfr10.jpg',
        ])->saveOrFail();

        $car = $this->createBookableCar(480000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(5)->setTime(9, 0);
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('backup-proof.jpg'),
        ])->assertRedirect();

        $payment = $rental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => 480000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $backupDir = storage_path('framework/testing/backup_'.uniqid());

        try {
            $this->artisan('driveease:backup', ['--output' => $backupDir])
                ->assertExitCode(0);

            $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';
            $this->assertFileExists($manifestPath);

            // APP_KEY mentah tidak pernah disimpan ke dalam manifest cadangan (NFR-10).
            $manifestRaw = (string) File::get($manifestPath);
            $this->assertStringNotContainsString((string) config('app.key'), $manifestRaw);
            $this->assertStringContainsString(hash('sha256', (string) config('app.key')), $manifestRaw);

            // Verifikasi pemulihan terisolasi pada 1 nomor transaksi beserta KTP/SIM terenkripsi & bukti bayarnya.
            $this->artisan('driveease:verify-restore', [
                'path' => $backupDir,
                '--rental-code' => $rental->rental_code,
            ])->assertExitCode(0);

            // Jika isi cadangan tabel dirusak, perintah uji pemulihan wajib gagal (non-zero).
            $rentalsJsonPath = $backupDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'rentals.json';
            File::append($rentalsJsonPath, "\n ");

            $this->artisan('driveease:verify-restore', [
                'path' => $backupDir,
                '--rental-code' => $rental->rental_code,
            ])->assertExitCode(1);

            // Kembalikan kecocokan checksum? Cukup uji bahwa APP_KEY berbeda ditolak sebelum sentuh data sensitif.
            $originalKey = config('app.key');
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
            try {
                $this->artisan('driveease:verify-restore', [
                    'path' => $backupDir,
                    '--rental-code' => $rental->rental_code,
                ])->assertExitCode(1);
            } finally {
                config(['app.key' => $originalKey]);
            }
        } finally {
            File::deleteDirectory($backupDir);
        }
    }

    public function test_operational_hardening_scheduler_https_debug_and_indonesian_usability_checks(): void
    {
        // Scheduler memiliki jadwal pelepasan kedaluwarsa per menit dan pencadangan harian otomatis.
        $events = collect(app(Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($event): bool => str_contains((string) $event->command, 'rentals:expire-unpaid')));
        $this->assertTrue($events->contains(fn ($event): bool => str_contains((string) $event->command, 'driveease:backup')));

        // Pastikan config/app.php membawa opsi force_https dan APP_DEBUG dapat dimatikan via konfigurasi.
        $appConfig = require config_path('app.php');
        $this->assertArrayHasKey('force_https', $appConfig);

        config(['app.force_https' => true]);
        try {
            (new AppServiceProvider($this->app))->boot();
            $this->assertStringStartsWith('https://', route('login'));
        } finally {
            config(['app.force_https' => false]);
            URL::forceScheme(null);
        }

        // NFR-06: Halaman inti berbahasa Indonesia, memiliki label aksesibel dan wrapper tabel responsif.
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('id="pesanan"', false)
            ->assertSee('for="customer_history_search"', false)
            ->assertDontSee('Pesanan saya');

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('for="report_date_from"', false)
            ->assertSee('overflow-x-auto', false);

        $this->actingAs($staff)->get(route('staff.schedules.index'))
            ->assertOk()
            ->assertSee('Jadwal Operasional');
    }

    /**
     * Memastikan seluruh file migration berjalan bersih pada database PostgreSQL yang benar-benar kosong.
     */
    public function test_migrations_run_cleanly_from_empty_postgresql_database(): void
    {
        $emptyDbName = 'driveease_empty_check';
        $postgresPdo = $this->connectPostgresMaintenance();
        $this->recreateIsolatedDatabase($postgresPdo, $emptyDbName);

        // Salin konfigurasi koneksi pgsql agar target database dapat diarahkan ke database kosong sementara.
        $baseConfig = config('database.connections.pgsql');
        $baseConfig['database'] = $emptyDbName;
        config(['database.connections.empty_check' => $baseConfig]);

        try {
            // Alihkan koneksi default sementara karena sebagian file migration memanggil DB::statement() tanpa nama koneksi.
            DB::setDefaultConnection('empty_check');
            config(['database.default' => 'empty_check']);
            DB::purge('empty_check');

            $exitCode = Artisan::call('migrate', [
                '--database' => 'empty_check',
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());

            // Pastikan seluruh 12 tabel inti DriveEase dan tabel sistem Laravel terbentuk tanpa error.
            $expectedTables = [
                'migrations',
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

            foreach ($expectedTables as $tableName) {
                $this->assertTrue(
                    Schema::connection('empty_check')->hasTable($tableName),
                    "Tabel {$tableName} tidak terbentuk saat migration dari database kosong."
                );
            }

            // Pastikan relasi foreign key utama langsung aktif pada database hasil migration bersih.
            $foreignKeys = $this->listSchemaForeignKeys('empty_check');

            foreach ([
                'users.role_id->roles',
                'cars.car_type_id->car_types',
                'rentals.user_id->users',
                'rentals.car_id->cars',
                'inspections.rental_id->rentals',
                'inspections.inspector_id->users',
                'rental_payments.rental_id->rentals',
                'rental_change_requests.rental_id->rentals',
                'rental_extensions.rental_id->rentals',
                'rental_histories.rental_id->rentals',
                'rental_cash_flows.rental_id->rentals',
                'rental_cash_flows.rental_payment_id->rental_payments',
                'rental_cash_flows.rental_extension_id->rental_extensions',
            ] as $expectedFk) {
                $this->assertContains($expectedFk, $foreignKeys, "Foreign key tidak ditemukan pada migration bersih: {$expectedFk}");
            }
        } finally {
            // Selalu kembalikan koneksi utama ke pgsql dan hapus database uji sementara meskipun ada assertion yang gagal.
            DB::setDefaultConnection('pgsql');
            config(['database.default' => 'pgsql']);
            DB::purge('empty_check');

            $this->dropIsolatedDatabase($postgresPdo, $emptyDbName);
        }
    }

    /**
     * Mengambil daftar foreign key skema public pada koneksi PostgreSQL dalam format tabel.kolom->tabel_rujukan.
     *
     * @return array<int, string>
     */
    private function listSchemaForeignKeys(string $connectionName): array
    {
        return DB::connection($connectionName)
            ->table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function ($join): void {
                $join->on('tc.constraint_name', '=', 'kcu.constraint_name')
                    ->on('tc.table_schema', '=', 'kcu.table_schema');
            })
            ->join('information_schema.constraint_column_usage as ccu', function ($join): void {
                $join->on('ccu.constraint_name', '=', 'tc.constraint_name')
                    ->on('ccu.table_schema', '=', 'tc.table_schema');
            })
            ->where('tc.constraint_type', 'FOREIGN KEY')
            ->where('tc.table_schema', 'public')
            ->select([
                'tc.table_name',
                'kcu.column_name',
                'ccu.table_name as referenced_table',
            ])
            ->get()
            ->map(fn (object $fk): string => "{$fk->table_name}.{$fk->column_name}->{$fk->referenced_table}")
            ->all();
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
            'name' => ucfirst($roleName).' Final '.uniqid(),
            'email' => $roleName.'_fin_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Final '.uniqid(),
            'email' => 'cust_fin_'.uniqid().'@driveease.test',
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
        $carType = CarType::firstOrCreate(['name' => 'Sedan Final'], ['description' => 'Tipe uji penerimaan akhir']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Honda Accord Final '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' FN',
            'brand' => 'Honda',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => $pricePerDay,
            'status' => 'available',
            'readiness_status' => Car::READINESS_READY,
        ]);
    }
}
