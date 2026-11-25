<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalScheduleService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalScheduleAndPricingTest extends TestCase
{
    use DatabaseTransactions;

    private RentalScheduleService $scheduleService;

    private RentalBookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Uji No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji resmi.',
            'bank_name' => 'Bank Uji DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Uji',
        ], ['id']);

        $this->scheduleService = app(RentalScheduleService::class);
        $this->bookingService = app(RentalBookingService::class);
    }

    public function test_pricing_uses_wib_24_hour_days_snapshots_tariff_and_late_pickup_does_not_shift_return(): void
    {
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();
        $originalPrice = (float) $car->price_per_day;

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(10, 30, 0);

        $rental = $this->bookingService->createBooking(
            customer: $customer,
            carId: $car->id,
            pickupAt: $pickupAt,
            totalDays: 3,
            termsAccepted: true,
        );

        // 3 hari x 24 jam = 72 jam tepat sejak jadwal pengambilan (WIB).
        $expectedReturn = $pickupAt->addHours(72);
        $this->assertTrue($rental->pickup_at->timezone('Asia/Jakarta')->equalTo($pickupAt));
        $this->assertTrue($rental->return_at->timezone('Asia/Jakarta')->equalTo($expectedReturn));
        $this->assertSame(3, $rental->total_days);
        $this->assertEquals($originalPrice, (float) $rental->daily_price);
        $this->assertEquals($originalPrice * 3, (float) $rental->total_amount);

        // Perubahan tarif kendaraan setelah pemesanan tidak mengubah tarif pesanan lama.
        $car->update(['price_per_day' => $originalPrice + 250000]);
        $this->assertEquals($originalPrice, (float) $rental->fresh()->daily_price);
        $this->assertEquals($originalPrice * 3, (float) $rental->fresh()->total_amount);

        // Keterlambatan pengambilan aktual (2 jam terlambat) tidak menggeser return_at.
        $rental->recordActualPickup($pickupAt->addHours(2));
        $rental->save();
        $this->assertTrue($rental->fresh()->return_at->timezone('Asia/Jakarta')->equalTo($expectedReturn));
    }

    public function test_one_hour_preparation_buffer_blocks_59_minutes_and_allows_60_minutes_on_both_sides(): void
    {
        $customerA = $this->createVerifiedCustomer();
        $customerB = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();

        $existingPickup = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
        $existingRental = $this->bookingService->createBooking(
            customer: $customerA,
            carId: $car->id,
            pickupAt: $existingPickup,
            totalDays: 1,
            termsAccepted: true,
        );
        $existingReturn = $existingRental->return_at->timezone('Asia/Jakarta');

        // 59 menit setelah waktu kembali masih masuk jeda persiapan -> harus ditolak.
        try {
            $this->bookingService->createBooking(
                customer: $customerB,
                carId: $car->id,
                pickupAt: $existingReturn->addMinutes(59),
                totalDays: 1,
                termsAccepted: true,
            );
            $this->fail('Seharusnya menolak pemesanan dengan jeda 59 menit setelah pengembalian.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup_at', $exception->errors());
        }

        // Tepat 60 menit setelah waktu kembali -> harus diterima.
        $afterRental = $this->bookingService->createBooking(
            customer: $customerB,
            carId: $car->id,
            pickupAt: $existingReturn->addMinutes(60),
            totalDays: 1,
            termsAccepted: true,
        );
        $this->assertSame(Rental::STATUS_PENDING, $afterRental->rental_status);

        // 1 hari berakhir 59 menit sebelum existingPickup -> harus ditolak.
        try {
            $this->bookingService->createBooking(
                customer: $customerB,
                carId: $car->id,
                pickupAt: $existingPickup->subHours(24)->subMinutes(59),
                totalDays: 1,
                termsAccepted: true,
            );
            $this->fail('Seharusnya menolak pemesanan dengan jeda 59 menit sebelum pengambilan berikutnya.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup_at', $exception->errors());
        }

        // 1 hari berakhir tepat 60 menit sebelum existingPickup -> harus diterima.
        $beforeRental = $this->bookingService->createBooking(
            customer: $customerB,
            carId: $car->id,
            pickupAt: $existingPickup->subHours(24)->subMinutes(60),
            totalDays: 1,
            termsAccepted: true,
        );
        $this->assertSame(Rental::STATUS_PENDING, $beforeRental->rental_status);
    }

    public function test_two_different_vehicles_can_be_booked_for_the_exact_same_period(): void
    {
        $customer = $this->createVerifiedCustomer();
        $cars = Car::readyForRental()->take(2)->get();
        $this->assertCount(2, $cars);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(9, 0, 0);

        $rentalOne = $this->bookingService->createBooking($customer, $cars[0]->id, $pickupAt, 2, true);
        $rentalTwo = $this->bookingService->createBooking($customer, $cars[1]->id, $pickupAt, 2, true);

        $this->assertNotSame($rentalOne->car_id, $rentalTwo->car_id);
        $this->assertSame(Rental::STATUS_PENDING, $rentalOne->rental_status);
        $this->assertSame(Rental::STATUS_PENDING, $rentalTwo->rental_status);
    }

    public function test_maintenance_and_archived_vehicles_are_rejected(): void
    {
        $customer = $this->createVerifiedCustomer();
        $carType = CarType::firstOrFail();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(12, 0, 0);

        $maintenanceCar = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Servis Booking',
            'brand' => 'Honda',
            'plate_number' => 'B 4001 MNT',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'maintenance',
            'maintenance_reason' => 'Servis mesin',
        ]);

        $archivedCar = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Mobil Arsip Booking',
            'brand' => 'Toyota',
            'plate_number' => 'B 4002 ARC',
            'model_year' => 2022,
            'transmission' => 'manual',
            'capacity' => 5,
            'price_per_day' => 450000,
            'status' => 'available',
        ]);
        $archivedCar->archived_at = now();
        $archivedCar->save();

        foreach ([$maintenanceCar, $archivedCar] as $unavailableCar) {
            try {
                $this->bookingService->createBooking($customer, $unavailableCar->id, $pickupAt, 1, true);
                $this->fail('Unit tidak siap sewa tidak boleh dipesan.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('car_id', $exception->errors());
            }
        }
    }

    public function test_expired_unpaid_holds_release_schedule_idempotently_while_submitted_proof_stays_held(): void
    {
        $customerA = $this->createVerifiedCustomer();
        $customerB = $this->createVerifiedCustomer();
        $cars = Car::readyForRental()->take(2)->get();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(5)->setTime(10, 0, 0);

        // Pesanan 1: unpaid tanpa bukti dan tenggatnya sudah lewat.
        $expiredBooking = $this->bookingService->createBooking($customerA, $cars[0]->id, $pickupAt, 1, true);
        $expiredBooking->payment_deadline_at = CarbonImmutable::now('Asia/Jakarta')->subMinute();
        $expiredBooking->save();

        // Pesanan 2: bukti sudah diunggah tepat waktu (pending_approval) -> tetap menahan jadwal.
        $proofSubmittedBooking = $this->bookingService->createBooking($customerA, $cars[1]->id, $pickupAt, 1, true);
        $proofSubmittedBooking->payment_proof = 'payments/bukti-transfer.jpg';
        $proofSubmittedBooking->transitionPaymentStatus(Rental::PAYMENT_PENDING_APPROVAL);
        $proofSubmittedBooking->payment_deadline_at = CarbonImmutable::now('Asia/Jakarta')->subMinute();
        $proofSubmittedBooking->save();

        // Jalankan command dua kali untuk membuktikan idempotensi.
        $this->artisan('rentals:expire-unpaid')->assertSuccessful();
        $this->artisan('rentals:expire-unpaid')->assertSuccessful();

        $expiredBooking->refresh();
        $this->assertSame(Rental::STATUS_EXPIRED, $expiredBooking->rental_status);
        $this->assertNotNull($expiredBooking->cancelled_at);
        $this->assertDatabaseHas('rentals', ['id' => $expiredBooking->id, 'rental_code' => $expiredBooking->rental_code]);

        // Unit pertama kini bisa dipesan pelanggan lain pada periode yang sama.
        $replacementBooking = $this->bookingService->createBooking($customerB, $cars[0]->id, $pickupAt, 1, true);
        $this->assertSame(Rental::STATUS_PENDING, $replacementBooking->rental_status);

        // Unit kedua tetap ditahan karena bukti sudah dikirim.
        $proofSubmittedBooking->refresh();
        $this->assertSame(Rental::STATUS_PENDING, $proofSubmittedBooking->rental_status);
        $this->assertTrue($this->scheduleService->hasScheduleConflict($cars[1]->id, $pickupAt, $pickupAt->addHours(24)));
    }

    public function test_status_transitions_enforce_allowed_paths_and_idempotency_key_prevents_duplicates(): void
    {
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(14, 0, 0);

        $first = $this->bookingService->createBooking(
            customer: $customer,
            carId: $car->id,
            pickupAt: $pickupAt,
            totalDays: 2,
            termsAccepted: true,
            idempotencyKey: 'key-idemp-123',
        );

        $second = $this->bookingService->createBooking(
            customer: $customer,
            carId: $car->id,
            pickupAt: $pickupAt,
            totalDays: 2,
            termsAccepted: true,
            idempotencyKey: 'key-idemp-123',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Rental::where('idempotency_key', 'key-idemp-123')->count());

        // Transisi sah.
        $first->transitionRentalStatus(Rental::STATUS_CONFIRMED);
        $first->transitionPaymentStatus(Rental::PAYMENT_PENDING_APPROVAL);
        $first->transitionPaymentStatus(Rental::PAYMENT_PAID);
        $this->assertSame(Rental::STATUS_CONFIRMED, $first->rental_status);
        $this->assertSame(Rental::PAYMENT_PAID, $first->payment_status);

        // Transisi ilegal (melompat langsung dari confirmed ke completed) harus ditolak.
        $this->expectException(DomainException::class);
        $first->transitionRentalStatus(Rental::STATUS_COMPLETED);
    }

    private function createVerifiedCustomer(): User
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $customerRole->id,
            'name' => 'Pelanggan Uji '.uniqid(),
            'email' => 'booking_test_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
