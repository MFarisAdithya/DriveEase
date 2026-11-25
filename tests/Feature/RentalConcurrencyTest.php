<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentalConcurrencyTest extends TestCase
{
    // Uji konkurensi nyata memakai koneksi database terpisah antar-proses sehingga
    // tidak dibungkus DatabaseTransactions; pembersihan baris dilakukan manual pada blok finally.

    public function test_concurrent_database_connections_prevent_double_booking_for_same_vehicle_and_period(): void
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);
        $carType = CarType::firstOrCreate(['name' => 'Sedan']);

        $customerA = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Concurrent A',
            'email' => 'conc_a_'.uniqid().'@driveease.test',
            'phone' => '081234567891',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $customerB = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Concurrent B',
            'email' => 'conc_b_'.uniqid().'@driveease.test',
            'phone' => '081234567892',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678902',
            'sim_number' => '12345678901235',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $car = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Konkurensi Nyata',
            'brand' => 'Toyota',
            'plate_number' => 'B '.rand(1000, 9999).' CNC',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'available',
        ]);

        $hadSettings = DB::table('rental_settings')->where('id', 1)->first();
        if ($hadSettings === null) {
            DB::table('rental_settings')->insert([
                'id' => 1,
                'business_name' => 'DriveEase Uji Konkurensi',
                'services' => 'Sewa mobil',
                'address' => 'Jl. Uji 1',
                'contact' => '08123456789',
                'operating_hours' => '08.00-21.00',
                'terms' => 'Ketentuan uji.',
                'bank_name' => 'Bank Uji',
                'account_number' => '123456789012',
                'account_holder' => 'PT Uji',
            ]);
        }

        $pickupIso = CarbonImmutable::now('Asia/Jakarta')->addDays(6)->setTime(10, 0, 0)->format('Y-m-d H:i:s');
        $barrierTime = microtime(true) + 0.6;

        try {
            $results = $this->runConcurrentBookingWorkers([
                ['customer_id' => $customerA->id, 'car_id' => $car->id, 'pickup' => $pickupIso, 'days' => 2, 'channel' => 'online', 'key' => 'key-a-'.uniqid(), 'barrier' => $barrierTime],
                ['customer_id' => $customerB->id, 'car_id' => $car->id, 'pickup' => $pickupIso, 'days' => 2, 'channel' => 'walk_in', 'key' => 'key-b-'.uniqid(), 'barrier' => $barrierTime],
            ]);

            $statuses = array_column($results, 'status');
            $this->assertCount(1, array_keys($statuses, 'ok', true), 'Hanya satu transaksi boleh berhasil: '.json_encode($results));
            $this->assertCount(1, array_keys($statuses, 'validation_error', true), 'Permintaan bentrok kedua harus mendapat ValidationException: '.json_encode($results));
            $this->assertSame(1, Rental::where('car_id', $car->id)->count());
        } finally {
            Rental::where('car_id', $car->id)->delete();
            $car->delete();
            $customerA->delete();
            $customerB->delete();
            if ($hadSettings === null) {
                DB::table('rental_settings')->where('id', 1)->delete();
            }
        }
    }

    public function test_concurrent_identical_idempotency_key_returns_single_booking_without_error(): void
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);
        $carType = CarType::firstOrCreate(['name' => 'Sedan']);

        $customer = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Idemp Customer',
            'email' => 'idemp_'.uniqid().'@driveease.test',
            'phone' => '081234567893',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678903',
            'sim_number' => '12345678901236',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $car = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Idempotency',
            'brand' => 'Honda',
            'plate_number' => 'B '.rand(1000, 9999).' IDM',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 550000,
            'status' => 'available',
        ]);

        $hadSettings = DB::table('rental_settings')->where('id', 1)->first();
        if ($hadSettings === null) {
            DB::table('rental_settings')->insert([
                'id' => 1,
                'business_name' => 'DriveEase Uji Idempotency',
                'services' => 'Sewa mobil',
                'address' => 'Jl. Uji 1',
                'contact' => '08123456789',
                'operating_hours' => '08.00-21.00',
                'terms' => 'Ketentuan uji.',
                'bank_name' => 'Bank Uji',
                'account_number' => '123456789012',
                'account_holder' => 'PT Uji',
            ]);
        }

        $sharedKey = 'shared-idemp-'.uniqid();
        $pickupIso = CarbonImmutable::now('Asia/Jakarta')->addDays(7)->setTime(11, 0, 0)->format('Y-m-d H:i:s');
        $barrierTime = microtime(true) + 0.6;

        try {
            $results = $this->runConcurrentBookingWorkers([
                ['customer_id' => $customer->id, 'car_id' => $car->id, 'pickup' => $pickupIso, 'days' => 1, 'channel' => 'online', 'key' => $sharedKey, 'barrier' => $barrierTime],
                ['customer_id' => $customer->id, 'car_id' => $car->id, 'pickup' => $pickupIso, 'days' => 1, 'channel' => 'online', 'key' => $sharedKey, 'barrier' => $barrierTime],
            ]);

            $this->assertSame(['ok', 'ok'], array_column($results, 'status'), json_encode($results));
            $this->assertSame($results[0]['rental_id'], $results[1]['rental_id']);
            $this->assertSame(1, Rental::where('idempotency_key', $sharedKey)->count());
        } finally {
            Rental::where('car_id', $car->id)->delete();
            $car->delete();
            $customer->delete();
            if ($hadSettings === null) {
                DB::table('rental_settings')->where('id', 1)->delete();
            }
        }
    }

    public function test_booking_is_rejected_without_valid_payment_account_and_rented_car_allows_future_booking_and_scheduler_expires(): void
    {
        // Jalankan skenario dalam transaksi manual via DB::beginTransaction()/rollBack() agar data uji tidak tersisa.
        DB::beginTransaction();

        try {
            $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
            $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

            $admin = User::create([
                'role_id' => $adminRole->id,
                'name' => 'Admin Review',
                'email' => 'admin_rev_'.uniqid().'@driveease.test',
                'password' => Hash::make('password123'),
            ]);

            $customer = User::create([
                'role_id' => $customerRole->id,
                'name' => 'Customer Review',
                'email' => 'cust_rev_'.uniqid().'@driveease.test',
                'phone' => '081234567890',
                'password' => Hash::make('password123'),
                'ktp_number' => '3174012345678909',
                'sim_number' => '12345678901239',
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]);

            $car = Car::activeInventory()->firstOrFail();

            // 1. Tanpa baris rental_settings atau rekening tidak valid, pemesanan online & walk-in ditolak.
            DB::table('rental_settings')->delete();
            $futurePickup = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0);

            $this->actingAs($customer)->post(route('customer.bookings.store'), [
                'car_id' => $car->id,
                'pickup_at' => $futurePickup->format('Y-m-d\TH:i'),
                'total_days' => 1,
                'accept_terms' => '1',
            ])->assertSessionHasErrors('payment_account');

            $this->actingAs($admin)->post(route('admin.bookings.store'), [
                'customer_id' => $customer->id,
                'car_id' => $car->id,
                'pickup_at' => $futurePickup->format('Y-m-d\TH:i'),
                'total_days' => 1,
                'accept_terms' => '1',
            ])->assertSessionHasErrors('payment_account');

            // Simpan rekening valid untuk uji berikutnya.
            DB::table('rental_settings')->insert([
                'id' => 1,
                'business_name' => 'DriveEase Review',
                'services' => 'Sewa mobil',
                'address' => 'Jl. Uji 1',
                'contact' => '08123456789',
                'operating_hours' => '08.00-21.00',
                'terms' => 'Ketentuan uji.',
                'bank_name' => 'Bank Uji',
                'account_number' => '555666777888',
                'account_holder' => 'PT DriveEase',
            ]);

            // 2. Unit berstatus 'rented' saat ini tetap bisa dipesan untuk periode mendatang yang kosong.
            $car->update(['status' => 'rented']);
            $this->assertFalse($car->fresh()->isReadyForHandover());
            $this->assertTrue($car->fresh()->isBookableForSchedule());

            $activeNowPickup = CarbonImmutable::now('Asia/Jakarta')->subHours(2);
            $activeRental = new Rental([
                'rental_code' => 'DRV-ACT-'.substr(uniqid(), -8),
                'user_id' => $customer->id,
                'car_id' => $car->id,
                'start_date' => $activeNowPickup->toDateString(),
                'end_date' => $activeNowPickup->addHours(24)->toDateString(),
                'pickup_at' => $activeNowPickup,
                'return_at' => $activeNowPickup->addHours(24),
                'total_days' => 1,
                'daily_price' => $car->price_per_day,
                'total_amount' => $car->price_per_day,
                'booking_channel' => Rental::CHANNEL_ONLINE,
            ]);
            $activeRental->rental_status = Rental::STATUS_PICKED_UP;
            $activeRental->payment_status = Rental::PAYMENT_PAID;
            $activeRental->save();

            $nonOverlappingFuture = CarbonImmutable::now('Asia/Jakarta')->addDays(5)->setTime(10, 0);
            $this->getJson(route('api.v1.availability', [
                'pickup_at' => $nonOverlappingFuture->format('Y-m-d\TH:i'),
                'total_days' => 1,
            ]))->assertOk()->assertJsonFragment(['id' => $car->id]);

            $this->actingAs($customer)->post(route('customer.bookings.store'), [
                'car_id' => $car->id,
                'pickup_at' => $nonOverlappingFuture->format('Y-m-d\TH:i'),
                'total_days' => 1,
                'accept_terms' => '1',
            ])->assertRedirect();
        } finally {
            DB::rollBack();
        }
    }

    public function test_scheduler_registers_and_runs_expire_unpaid_rentals_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'rentals:expire-unpaid'));
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);

        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);
        $carType = CarType::firstOrCreate(['name' => 'Sedan']);

        $customer = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Scheduler Customer',
            'email' => 'sched_'.uniqid().'@driveease.test',
            'phone' => '081234567894',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678904',
            'sim_number' => '12345678901237',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $car = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Scheduler',
            'brand' => 'Toyota',
            'plate_number' => 'B '.rand(1000, 9999).' SCH',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'available',
        ]);

        $hadSettings = DB::table('rental_settings')->where('id', 1)->first();
        if ($hadSettings === null) {
            DB::table('rental_settings')->insert([
                'id' => 1,
                'business_name' => 'DriveEase Uji Scheduler',
                'services' => 'Sewa mobil',
                'address' => 'Jl. Uji 1',
                'contact' => '08123456789',
                'operating_hours' => '08.00-21.00',
                'terms' => 'Ketentuan uji.',
                'bank_name' => 'Bank Uji',
                'account_number' => '123456789012',
                'account_holder' => 'PT Uji',
            ]);
        }

        try {
            $overdueBooking = app(RentalBookingService::class)->createBooking(
                customer: $customer,
                carId: $car->id,
                pickupAt: CarbonImmutable::now('Asia/Jakarta')->addDays(8)->setTime(10, 0),
                totalDays: 1,
                termsAccepted: true,
            );
            $overdueBooking->payment_deadline_at = CarbonImmutable::now('Asia/Jakarta')->subMinutes(2);
            $overdueBooking->save();

            // schedule:run mengeksekusi subprocess `php artisan rentals:expire-unpaid` secara nyata.
            $this->artisan('schedule:run')->assertSuccessful();
            $this->assertSame(Rental::STATUS_EXPIRED, $overdueBooking->fresh()->rental_status);
        } finally {
            Rental::where('car_id', $car->id)->delete();
            $car->delete();
            $customer->delete();
            if ($hadSettings === null) {
                DB::table('rental_settings')->where('id', 1)->delete();
            }
        }
    }

    /**
     * Spawn multiple PHP CLI subprocesses hitting PostgreSQL driveease_test concurrently.
     *
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array<string, mixed>>
     */
    private function runConcurrentBookingWorkers(array $jobs): array
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payload = json_decode(base64_decode($argv[1]), true);
$remainingUs = (int) (($payload['barrier'] - microtime(true)) * 1000000);
if ($remainingUs > 0) {
    usleep($remainingUs);
}

try {
    $customer = App\Models\User::findOrFail($payload['customer_id']);
    $pickup = Carbon\CarbonImmutable::parse($payload['pickup'], 'Asia/Jakarta');
    $rental = app(App\Services\RentalBookingService::class)->createBooking(
        customer: $customer,
        carId: (int) $payload['car_id'],
        pickupAt: $pickup,
        totalDays: (int) $payload['days'],
        termsAccepted: true,
        channel: $payload['channel'],
        idempotencyKey: $payload['key'],
    );
    echo json_encode(['status' => 'ok', 'rental_id' => $rental->id]);
} catch (Illuminate\Validation\ValidationException $e) {
    echo json_encode(['status' => 'validation_error', 'errors' => $e->errors()]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'class' => get_class($e), 'message' => $e->getMessage()]);
}
PHP;

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'driveease_test',
            'DB_USERNAME' => 'postgres',
            'DB_PASSWORD' => '',
        ];

        $processes = [];
        foreach ($jobs as $job) {
            $encoded = base64_encode(json_encode($job, JSON_THROW_ON_ERROR));
            $process = new Process([PHP_BINARY, '-r', $script, $encoded], base_path(), $env);
            $process->start();
            $processes[] = $process;
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $decoded = json_decode(trim($process->getOutput()), true);
            $results[] = is_array($decoded)
                ? $decoded
                : ['status' => 'process_failed', 'stdout' => $process->getOutput(), 'stderr' => $process->getErrorOutput()];
        }

        return $results;
    }
}
