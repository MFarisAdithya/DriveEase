<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\TuroFleetSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TuroFleetReplacementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_turo_reference_fleet_replaces_old_cars_and_resolves_local_photos(): void
    {
        $sedanType = CarType::firstOrCreate(['name' => 'Sedan']);

        // Sisipkan unit lama tanpa transaksi sewa agar pengujian memastikan seeder
        // benar-benar mengganti armada lama menjadi 10 kendaraan referensi Turo.
        Car::create([
            'car_type_id' => $sedanType->id,
            'name' => 'Toyota Avanza 1.5 G',
            'brand' => 'Toyota',
            'plate_number' => 'B 1234 EAA',
            'model_year' => 2023,
            'transmission' => 'manual',
            'capacity' => 7,
            'price_per_day' => 400000,
            'status' => 'available',
        ]);

        Car::create([
            'car_type_id' => $sedanType->id,
            'name' => 'Honda Civic RS',
            'brand' => 'Honda',
            'plate_number' => 'B 4567 HAD',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 900000,
            'status' => 'available',
        ]);

        $this->seed(TuroFleetSeeder::class);

        $expectedModels = [
            'Toyota Corolla LE' => 'toyota-corolla-le.webp',
            'Subaru WRX Base' => 'subaru-wrx-base.webp',
            'Volkswagen Tiguan SE' => 'volkswagen-tiguan-se.webp',
            'Kia Telluride EX X-Line' => 'kia-telluride-ex.webp',
            'Chrysler Pacifica Touring L' => 'chrysler-pacifica.webp',
            'Honda Odyssey EX-L' => 'honda-odyssey-exl.webp',
            'Kia Soul LX' => 'kia-soul-lx.webp',
            'Polestar 4 Base' => 'polestar-4-base.webp',
            'Tesla Model 3 Mid Range' => 'tesla-model-3.webp',
            'Porsche 911 Carrera' => 'porsche-911-carrera.webp',
        ];

        $cars = Car::activeInventory()->get()->keyBy('name');

        $this->assertCount(10, $cars);

        foreach ($expectedModels as $name => $filename) {
            $car = $cars->get($name);
            $this->assertNotNull($car, "Missing expected Turo vehicle: {$name}");

            $photo = $car->resolvePhoto();
            $this->assertNotNull($photo['url']);
            $this->assertStringContainsString($filename, $photo['url']);
            $this->assertFileExists(public_path('images/models/'.$filename));
        }

        $this->assertDatabaseMissing('cars', ['name' => 'Toyota Avanza 1.5 G']);
        $this->assertDatabaseMissing('cars', ['name' => 'Honda Civic RS']);
    }

    public function test_seeder_preserves_customer_accounts_and_verification_documents(): void
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        $customer = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Pelanggan Terjaga',
            'email' => 'keep_docs_'.uniqid().'@driveease.test',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'ktp_image' => 'identities/keep-ktp.jpg',
            'sim_image' => 'identities/keep-sim.jpg',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->seed(TuroFleetSeeder::class);

        $customer->refresh();
        $this->assertEquals('3174012345678901', $customer->ktp_number);
        $this->assertEquals('12345678901234', $customer->sim_number);
        $this->assertEquals('identities/keep-ktp.jpg', $customer->ktp_image);
        $this->assertEquals('identities/keep-sim.jpg', $customer->sim_image);
        $this->assertEquals('verified', $customer->verification_status);
    }

    public function test_seeder_archives_legacy_car_with_rental_history_without_overwriting_attributes(): void
    {
        $customer = $this->resolveCustomerUser();
        $sedanType = CarType::firstOrCreate(['name' => 'Sedan']);

        // Buat mobil lama dengan atribut khusus lalu hubungkan ke tabel rentals agar
        // pengujian memverifikasi seeder mengarsipkan unit tanpa menimpa atribut aslinya.
        $legacyCar = Car::create([
            'car_type_id' => $sedanType->id,
            'name' => 'Legacy History Car',
            'brand' => 'Legacy',
            'plate_number' => 'B 9999 LEG',
            'model_year' => 2020,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 450000,
            'status' => 'available',
            'description' => 'Unit lama dengan histori.',
        ]);

        $rentalId = $this->createRentalForCar($customer, $legacyCar, 450000);

        $this->seed(TuroFleetSeeder::class);

        $preservedRental = DB::table('rentals')->where('id', $rentalId)->first();
        $this->assertNotNull($preservedRental);
        $this->assertSame($legacyCar->id, $preservedRental->car_id);

        $legacyCar->refresh();
        $this->assertNotNull($legacyCar->archived_at);
        $this->assertSame($sedanType->id, $legacyCar->car_type_id);
        $this->assertSame('Legacy History Car', $legacyCar->name);
        $this->assertSame('Legacy', $legacyCar->brand);
        $this->assertSame('B 9999 LEG', $legacyCar->plate_number);
        $this->assertSame(2020, $legacyCar->model_year);
        $this->assertSame('automatic', $legacyCar->transmission);
        $this->assertSame(5, $legacyCar->capacity);
        $this->assertSame(450000, (int) $legacyCar->price_per_day);
        $this->assertSame('available', $legacyCar->status);
        $this->assertSame('Unit lama dengan histori.', $legacyCar->description);

        $this->assertSame(10, Car::activeInventory()->count());
        $corolla = Car::activeInventory()->where('name', 'Toyota Corolla LE')->firstOrFail();
        $this->assertSame('B 1421 TUA', $corolla->plate_number);

        $this->get(route('cars.index', ['search' => 'Legacy History Car']))
            ->assertOk()
            ->assertSee('0 mobil')
            ->assertDontSee(route('cars.show', $legacyCar));
        $this->get(route('cars.show', $legacyCar))->assertNotFound();

        // Jalankan ulang seeder pada waktu berbeda untuk memastikan idempotensi:
        // waktu archived_at dan atribut unit lama tidak berubah serta jumlah armada tetap 10.
        $firstArchivedAt = $legacyCar->archived_at;
        $this->travel(2)->minutes();
        $this->seed(TuroFleetSeeder::class);

        $legacyCarAfterSecondRun = $legacyCar->fresh();
        $this->assertNotNull($legacyCarAfterSecondRun->archived_at);
        $this->assertTrue($firstArchivedAt->equalTo($legacyCarAfterSecondRun->archived_at));
        $this->assertSame('Legacy History Car', $legacyCarAfterSecondRun->name);
        $this->assertSame('B 9999 LEG', $legacyCarAfterSecondRun->plate_number);
        $this->assertSame(10, Car::activeInventory()->count());
    }

    public function test_seeder_preserves_turo_car_with_rental_history_and_avoids_legacy_plate_collision(): void
    {
        $customer = $this->resolveCustomerUser();
        $sedanType = CarType::firstOrCreate(['name' => 'Sedan']);

        // Ubah salah satu unit Turo aktif lalu kaitkan ke histori rental agar seeder
        // mempertahankan tarif serta deskripsi historis tanpa membuat baris duplikat.
        $corolla = Car::activeInventory()->where('name', 'Toyota Corolla LE')->firstOrFail();
        $corolla->update([
            'price_per_day' => 625000,
            'description' => 'Tarif historis khusus',
        ]);
        $this->createRentalForCar($customer, $corolla, 625000);

        // Hapus unit Subaru tanpa histori sementara agar plat 'B 2532 TUB' dapat dipakai
        // oleh unit lama bersejarah untuk menguji pencegahan bentrok plat nomor unik.
        Car::where('plate_number', 'B 2532 TUB')->delete();

        $collidingLegacyCar = Car::create([
            'car_type_id' => $sedanType->id,
            'name' => 'Legacy Colliding Plate',
            'brand' => 'Legacy',
            'plate_number' => 'B 2532 TUB',
            'model_year' => 2019,
            'transmission' => 'manual',
            'capacity' => 5,
            'price_per_day' => 420000,
            'status' => 'available',
        ]);
        $this->createRentalForCar($customer, $collidingLegacyCar, 420000);

        $this->seed(TuroFleetSeeder::class);
        $this->seed(TuroFleetSeeder::class);

        $corolla->refresh();
        $this->assertNull($corolla->archived_at);
        $this->assertSame(625000, (int) $corolla->price_per_day);
        $this->assertSame('Tarif historis khusus', $corolla->description);
        $this->assertSame('B 1421 TUA', $corolla->plate_number);
        $this->assertSame(1, Car::activeInventory()->where('name', 'Toyota Corolla LE')->count());

        $collidingLegacyCar->refresh();
        $this->assertNotNull($collidingLegacyCar->archived_at);
        $this->assertSame('Legacy Colliding Plate', $collidingLegacyCar->name);
        $this->assertSame('Legacy', $collidingLegacyCar->brand);
        $this->assertSame('B 2532 TUB', $collidingLegacyCar->plate_number);

        $subaru = Car::activeInventory()->where('name', 'Subaru WRX Base')->firstOrFail();
        $this->assertNotSame('B 2532 TUB', $subaru->plate_number);
        $this->assertLessThanOrEqual(15, strlen($subaru->plate_number));
        $this->assertSame(10, Car::activeInventory()->count());
    }

    private function resolveCustomerUser(): User
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::whereHas('role', fn ($q) => $q->where('name', 'customer'))->first()
            ?? User::create([
                'role_id' => $customerRole->id,
                'name' => 'Rental Customer',
                'email' => 'rental_'.uniqid().'@driveease.test',
                'password' => Hash::make('password123'),
            ]);
    }

    private function createRentalForCar(User $customer, Car $car, int $dailyPrice): int
    {
        return DB::table('rentals')->insertGetId([
            'rental_code' => 'RNT-'.uniqid(),
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'total_days' => 2,
            'daily_price' => $dailyPrice,
            'total_amount' => $dailyPrice * 2,
            'payment_status' => 'paid',
            'rental_status' => 'returned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
