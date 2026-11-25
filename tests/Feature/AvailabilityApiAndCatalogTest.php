<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AvailabilityApiAndCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_api_returns_same_available_cars_and_tariffs_as_web_catalog_and_hides_private_data(): void
    {
        $customer = $this->createVerifiedCustomer();
        $cars = Car::readyForRental()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $cars);

        $blockedCar = $cars[0];
        $freeCar = $cars[1];

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);

        // Pesan mobil pertama agar tidak tersedia pada periode tersebut (termasuk jeda 1 jam).
        app(RentalBookingService::class)->createBooking(
            customer: $customer,
            carId: $blockedCar->id,
            pickupAt: $pickupAt->addHours(2),
            totalDays: 1,
            termsAccepted: true,
        );

        $queryParams = [
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 2,
        ];

        $apiResponse = $this->getJson(route('api.v1.availability', $queryParams))
            ->assertOk()
            ->assertJsonPath('period.timezone', 'Asia/Jakarta')
            ->assertJsonPath('period.total_days', 2)
            ->assertJsonPath('period.buffer_minutes', 60);

        $apiIds = collect($apiResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($blockedCar->id, $apiIds);
        $this->assertContains($freeCar->id, $apiIds);

        // Pastikan tarif API identik dengan kalkulasi katalog web dan bebas data privat.
        $freeCarPayload = collect($apiResponse->json('data'))->firstWhere('id', $freeCar->id);
        $this->assertSame((float) $freeCar->price_per_day, (float) $freeCarPayload['price_per_day']);
        $this->assertSame((float) $freeCar->price_per_day * 2, (float) $freeCarPayload['estimated_total']);
        $this->assertArrayNotHasKey('maintenance_reason', $freeCarPayload);
        $this->assertArrayNotHasKey('account_number', $freeCarPayload);
        $this->assertArrayNotHasKey('ktp_number', $freeCarPayload);

        // Katalog web untuk periode yang sama mengecualikan mobil yang bentrok dan menampilkan mobil tersedia.
        $this->get(route('cars.index', $queryParams))
            ->assertOk()
            ->assertSee($freeCar->name)
            ->assertDontSee(route('cars.show', $blockedCar));
    }

    public function test_api_returns_422_for_invalid_or_past_period(): void
    {
        $pastPickup = CarbonImmutable::now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i');

        $this->getJson(route('api.v1.availability', ['pickup_at' => $pastPickup, 'total_days' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_at']);

        $this->getJson(route('api.v1.availability', ['pickup_at' => CarbonImmutable::now('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i'), 'total_days' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total_days']);

        $this->getJson(route('api.v1.availability'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_at', 'total_days']);
    }

    public function test_catalog_preserves_period_filters_on_detail_return_link_and_checks_single_car_availability(): void
    {
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(9, 0, 0);

        $filters = [
            'search' => $car->brand,
            'car_type_id' => (string) $car->car_type_id,
            'transmission' => $car->transmission,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => '2',
        ];

        $this->get(route('cars.index', $filters))
            ->assertOk()
            ->assertSee(e(route('cars.show', ['car' => $car] + $filters)), false);

        $this->get(route('cars.show', ['car' => $car] + $filters))
            ->assertOk()
            ->assertSee(e(route('cars.index', $filters)), false)
            ->assertSee('Tersedia untuk periode yang dipilih')
            ->assertSee(number_format($car->price_per_day * 2, 0, ',', '.'));

        // Setelah dipesan, halaman detail menampilkan status tidak tersedia pada periode yang sama.
        app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 2, true);

        $this->get(route('cars.show', ['car' => $car] + $filters))
            ->assertOk()
            ->assertSee('Tidak tersedia pada periode yang dipilih');

        // Periode lampau di katalog dan detail ditangani dengan pesan kesalahan yang jelas (bukan 500).
        $pastFilters = ['pickup_at' => CarbonImmutable::now('Asia/Jakarta')->subHours(2)->format('Y-m-d\TH:i'), 'total_days' => '1'];
        $this->get(route('cars.index', $pastFilters))->assertOk()->assertSee('Filter periode sewa belum valid');
        $this->get(route('cars.show', ['car' => $car] + $pastFilters))->assertOk()->assertSee('masa mendatang');
    }

    public function test_api_excludes_maintenance_and_archived_vehicles(): void
    {
        $carType = CarType::firstOrFail();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(10, 0, 0);

        $maintenanceCar = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Perawatan API',
            'brand' => 'Honda',
            'plate_number' => 'B 6101 MNT',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'maintenance',
            'maintenance_reason' => 'Perbaikan kaki-kaki',
        ]);

        $archivedCar = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Arsip API',
            'brand' => 'Toyota',
            'plate_number' => 'B 6102 ARC',
            'model_year' => 2021,
            'transmission' => 'manual',
            'capacity' => 5,
            'price_per_day' => 450000,
            'status' => 'available',
        ]);
        $archivedCar->archived_at = now();
        $archivedCar->save();

        $response = $this->getJson(route('api.v1.availability', [
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 1,
        ]))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($maintenanceCar->id, $ids);
        $this->assertNotContains($archivedCar->id, $ids);
    }

    private function createVerifiedCustomer(): User
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $customerRole->id,
            'name' => 'Pelanggan API '.uniqid(),
            'email' => 'api_test_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
