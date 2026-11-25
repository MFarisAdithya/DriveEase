<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use DatabaseTransactions;

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
    }

    public function test_verified_customer_can_create_online_booking_and_snapshot_bank_and_tariff(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createCustomer('verified');
        $car = Car::readyForRental()->firstOrFail();

        // Admin menyimpan rekening tujuan aktif sebelum pemesanan terjadi.
        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji',
            'services' => 'Sewa mobil',
            'address' => 'Jl. Uji 1',
            'contact' => '08123456789',
            'operating_hours' => '08.00-21.00',
            'terms' => 'Syarat uji resmi.',
            'bank_name' => 'Bank Lama Uji',
            'account_number' => '111222333444',
            'account_holder' => 'DriveEase Lama',
        ], ['id']);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(10, 0, 0);

        $this->actingAs($customer)->get(route('customer.bookings.create', ['car_id' => $car->id]))->assertOk();

        // Sisipkan field ilegal untuk memastikan mass assignment protection server tetap utuh.
        $response = $this->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 2,
            'accept_terms' => '1',
            'notes' => 'Ambil tepat waktu',
            'daily_price' => 1000,
            'total_amount' => 1000,
            'payment_status' => 'paid',
            'rental_status' => 'confirmed',
            'user_id' => $admin->id,
        ]);

        $rental = Rental::where('car_id', $car->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.bookings.show', $rental));

        $this->assertSame($customer->id, $rental->user_id);
        $this->assertSame(Rental::CHANNEL_ONLINE, $rental->booking_channel);
        $this->assertSame(Rental::STATUS_PENDING, $rental->rental_status);
        $this->assertSame(Rental::PAYMENT_UNPAID, $rental->payment_status);
        $this->assertEquals((float) $car->price_per_day, (float) $rental->daily_price);
        $this->assertEquals((float) $car->price_per_day * 2, (float) $rental->total_amount);
        $this->assertNotNull($rental->terms_accepted_at);
        $this->assertNotNull($rental->payment_deadline_at);
        $this->assertSame('111222333444', $rental->payment_account_number);

        // Setelah admin mengganti rekening, tagihan pesanan lama tetap menyimpan rekening lama (A-13).
        $this->actingAs($admin)->patch(route('admin.rental-settings.update'), [
            'business_name' => 'DriveEase Uji',
            'services' => 'Sewa mobil',
            'address' => 'Jl. Uji 1',
            'contact' => '08123456789',
            'operating_hours' => '08.00-21.00',
            'terms' => 'Syarat uji resmi.',
            'bank_name' => 'Bank Baru Uji',
            'account_number' => '999888777666',
            'account_holder' => 'DriveEase Baru',
        ])->assertRedirect();

        $this->actingAs($customer)->get(route('customer.bookings.show', $rental))
            ->assertOk()
            ->assertSee($rental->rental_code)
            ->assertSee('111222333444')
            ->assertDontSee('999888777666');
    }

    public function test_online_booking_rejects_unverified_customers_past_dates_non_positive_days_and_unaccepted_terms(): void
    {
        $car = Car::readyForRental()->firstOrFail();
        $futurePickup = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i');

        foreach (['pending', 'rejected'] as $status) {
            $unverified = $this->createCustomer($status);
            $this->actingAs($unverified)->get(route('customer.bookings.create'))
                ->assertRedirect(route('customer.verification.create'));
            $this->post(route('customer.bookings.store'), [
                'car_id' => $car->id,
                'pickup_at' => $futurePickup,
                'total_days' => 1,
                'accept_terms' => '1',
            ])->assertRedirect(route('customer.verification.create'));
        }
        $this->assertSame(0, Rental::where('car_id', $car->id)->count());

        $verified = $this->createCustomer('verified');

        // Wajib menyetujui ketentuan, tidak boleh periode lampau, tidak boleh hari <= 0.
        $this->actingAs($verified)->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $futurePickup,
            'total_days' => 1,
        ])->assertSessionHasErrors('accept_terms');

        $this->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => CarbonImmutable::now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasErrors('pickup_at');

        $this->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $futurePickup,
            'total_days' => 0,
            'accept_terms' => '1',
        ])->assertSessionHasErrors('total_days');
    }

    public function test_customer_cannot_view_or_cancel_other_customers_booking_and_own_cancel_releases_schedule(): void
    {
        $owner = $this->createCustomer('verified');
        $other = $this->createCustomer('verified');
        $car = Car::readyForRental()->firstOrFail();
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(11, 0)->format('Y-m-d\TH:i');

        $this->actingAs($owner)->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $pickupAt,
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertRedirect();

        $rental = Rental::where('user_id', $owner->id)->latest('id')->firstOrFail();

        // Pelanggan lain ditolak (403).
        $this->actingAs($other)->get(route('customer.bookings.show', $rental))->assertForbidden();
        $this->patch(route('customer.bookings.cancel', $rental))->assertForbidden();

        // Pemilik membatalkan pesanan unpaid -> jadwal dilepas namun histori tetap tersimpan.
        $this->actingAs($owner)->patch(route('customer.bookings.cancel', $rental))
            ->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::STATUS_CANCELLED, $rental->rental_status);
        $this->assertNotNull($rental->cancelled_at);

        // Pelanggan lain kini dapat memesan mobil pada periode yang sama.
        $this->actingAs($other)->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $pickupAt,
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('rentals', ['user_id' => $other->id, 'car_id' => $car->id, 'rental_status' => Rental::STATUS_PENDING]);
    }

    public function test_admin_walk_in_booking_shares_rules_blocks_unverified_or_maintenance_and_prevents_cross_channel_overlap(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $unverified = $this->createCustomer('pending');
        $verifiedWalkIn = $this->createCustomer('verified');
        $verifiedOnline = $this->createCustomer('verified');

        $car = Car::readyForRental()->firstOrFail();
        $carType = CarType::firstOrFail();
        $maintenanceCar = Car::create([
            'car_type_id' => $carType->id,
            'name' => 'WalkIn Perawatan',
            'brand' => 'Kia',
            'plate_number' => 'B 7201 MNT',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'maintenance',
            'maintenance_reason' => 'Ganti oli',
        ]);

        // Staf dan pelanggan dilarang mengakses endpoint booking admin (403).
        $this->actingAs($staff)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($verifiedOnline)->get(route('admin.bookings.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('customer.bookings.create'))->assertForbidden();

        // Pendaftaran walk-in oleh admin membuat akun customer berstatus pending.
        $walkInEmail = 'walkin_new_'.uniqid().'@driveease.test';
        $this->actingAs($admin)->post(route('admin.bookings.register-customer'), [
            'name' => 'Pelanggan WalkIn Baru',
            'email' => $walkInEmail,
            'phone' => '081234567899',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();
        $newWalkInCustomer = User::where('email', $walkInEmail)->firstOrFail();
        $this->assertSame('pending', $newWalkInCustomer->verification_status);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(13, 0);

        // Pemesanan langsung menolak pelanggan belum terverifikasi dan mobil perawatan.
        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'customer_id' => $unverified->id,
            'car_id' => $car->id,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasErrors('customer_id');

        $this->post(route('admin.bookings.store'), [
            'customer_id' => $verifiedWalkIn->id,
            'car_id' => $maintenanceCar->id,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasErrors('car_id');

        // Pemesanan langsung berhasil untuk pelanggan terverifikasi, tetap berstatus unpaid/pending.
        $this->post(route('admin.bookings.store'), [
            'customer_id' => $verifiedWalkIn->id,
            'car_id' => $car->id,
            'pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertRedirect();

        $walkInRental = Rental::where('user_id', $verifiedWalkIn->id)->latest('id')->firstOrFail();
        $this->assertSame(Rental::CHANNEL_WALK_IN, $walkInRental->booking_channel);
        $this->assertSame($admin->id, $walkInRental->created_by);
        $this->assertSame(Rental::STATUS_PENDING, $walkInRental->rental_status);
        $this->assertSame(Rental::PAYMENT_UNPAID, $walkInRental->payment_status);

        // Pelanggan online tidak dapat memesan mobil yang sama pada jadwal bertumpuk atau jeda 30 menit.
        $this->actingAs($verifiedOnline)->post(route('customer.bookings.store'), [
            'car_id' => $car->id,
            'pickup_at' => $pickupAt->addHours(24)->addMinutes(30)->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasErrors('pickup_at');
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Uji '.uniqid(),
            'email' => $roleName.'_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createCustomer(string $verificationStatus): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer '.ucfirst($verificationStatus).' '.uniqid(),
            'email' => 'cust_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => $verificationStatus === 'verified' ? '3174012345678901' : null,
            'sim_number' => $verificationStatus === 'verified' ? '12345678901234' : null,
            'verification_status' => $verificationStatus,
            'verified_at' => $verificationStatus === 'verified' ? now() : null,
        ]);
    }
}
