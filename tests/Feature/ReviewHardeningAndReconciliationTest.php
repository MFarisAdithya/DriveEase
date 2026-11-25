<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalExtensionService;
use App\Services\RentalOperationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewHardeningAndReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Review',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Review No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji rekonsiliasi dan keamanan transaksi.',
            'bank_name' => 'Bank Awal Snapshot',
            'account_number' => '111222333444',
            'account_holder' => 'PT Snapshot Awal',
        ], ['id']);
    }

    public function test_admin_car_crud_enforces_operational_rules_readiness_and_impact_flagging(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $staff = $this->createUserWithRole('staff');
        $carType = CarType::firstOrCreate(['name' => 'MPV Hardening'], ['description' => 'Tipe uji hardening']);

        // Unit baru tidak boleh langsung disetel 'rented' melalui form tambah kendaraan admin.
        $this->actingAs($admin)->post(route('admin.cars.store'), [
            'name' => 'Mobil Ilegal Rented',
            'car_type_id' => $carType->id,
            'brand' => 'Toyota',
            'plate_number' => 'B '.random_int(1000, 9999).' HR',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 7,
            'price_per_day' => 500000,
            'status' => 'rented',
        ])->assertSessionHasErrors('status');

        $car = $this->createBookableCar(500000, $carType);

        // Kendaraan tanpa perjalanan picked_up tidak boleh dipaksa menjadi 'rented'.
        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'name' => $car->name,
            'car_type_id' => $carType->id,
            'brand' => $car->brand,
            'plate_number' => $car->plate_number,
            'model_year' => $car->model_year,
            'transmission' => $car->transmission,
            'capacity' => $car->capacity,
            'price_per_day' => 500000,
            'status' => 'rented',
        ])->assertSessionHasErrors('status');

        // Saat status kesiapan masih needs_preparation, pengubahan tarif pada unit available
        // tidak boleh otomatis melompati verifikasi persiapan staf menjadi ready.
        $car->forceFill([
            'readiness_status' => Car::READINESS_NEEDS_PREPARATION,
        ])->saveOrFail();

        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'name' => $car->name.' Facelift',
            'car_type_id' => $carType->id,
            'brand' => $car->brand,
            'plate_number' => $car->plate_number,
            'model_year' => $car->model_year,
            'transmission' => $car->transmission,
            'capacity' => $car->capacity,
            'price_per_day' => 550000,
            'status' => 'available',
        ])->assertRedirect(route('admin.cars.index'));

        $this->assertSame(Car::READINESS_NEEDS_PREPARATION, $car->fresh()->readiness_status);
        $this->assertSame(550000.0, (float) $car->fresh()->price_per_day);

        // Kembalikan ready untuk menguji booking terkonfirmasi terdampak perawatan & proteksi saat picked_up.
        $car->refresh()->forceFill(['readiness_status' => Car::READINESS_READY])->saveOrFail();

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0);
        $upcomingRental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $upcomingRental), [
            'payment_proof' => UploadedFile::fake()->image('upcoming.jpg'),
        ])->assertRedirect();
        $payment = $upcomingRental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $upcomingRental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        // Mengubah status unit menjadi maintenance via CRUD admin wajib menandai pesanan mendatang yang terdampak.
        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'name' => $car->name.' Facelift',
            'car_type_id' => $carType->id,
            'brand' => $car->brand,
            'plate_number' => $car->plate_number,
            'model_year' => $car->model_year,
            'transmission' => $car->transmission,
            'capacity' => $car->capacity,
            'price_per_day' => 550000,
            'status' => 'maintenance',
            'maintenance_reason' => 'Perawatan rem mendadak sebelum keberangkatan.',
        ])->assertRedirect(route('admin.cars.index'));

        $this->assertDatabaseHas('rental_histories', [
            'rental_id' => $upcomingRental->id,
            'action' => 'next_booking_impacted',
        ]);

        // Jika kendaraan benar-benar sedang dipakai (picked_up), admin tidak dapat mengubah statusnya lewat CRUD.
        $car->refresh()->forceFill([
            'status' => 'available',
            'readiness_status' => Car::READINESS_READY,
            'maintenance_reason' => null,
        ])->saveOrFail();
        $upcomingRental->forceFill([
            'pickup_at' => CarbonImmutable::now('Asia/Jakarta')->subHour(),
            'return_at' => CarbonImmutable::now('Asia/Jakarta')->addHours(23),
        ])->saveOrFail();
        app(RentalOperationService::class)->confirmPickup(
            $upcomingRental->fresh(),
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i'),
                'odometer_km' => 10000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('pickup-guard.jpg')
        );

        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'name' => $car->name,
            'car_type_id' => $carType->id,
            'brand' => $car->brand,
            'plate_number' => $car->plate_number,
            'model_year' => $car->model_year,
            'transmission' => $car->transmission,
            'capacity' => $car->capacity,
            'price_per_day' => 550000,
            'status' => 'available',
        ])->assertSessionHasErrors('status');

        // Unit yang sudah diarsipkan tidak dapat diubah status operasionalnya.
        $archivedCar = $this->createBookableCar(450000, $carType);
        $archivedCar->forceFill(['archived_at' => now()])->saveOrFail();

        $this->actingAs($admin)->put(route('admin.cars.update', $archivedCar), [
            'name' => $archivedCar->name,
            'car_type_id' => $carType->id,
            'brand' => $archivedCar->brand,
            'plate_number' => $archivedCar->plate_number,
            'model_year' => $archivedCar->model_year,
            'transmission' => $archivedCar->transmission,
            'capacity' => $archivedCar->capacity,
            'price_per_day' => 450000,
            'status' => 'maintenance',
            'maintenance_reason' => 'Coba ubah status arsip.',
        ])->assertSessionHasErrors('status');
    }

    public function test_archiving_car_is_blocked_while_active_or_upcoming_bookings_exist_and_allowed_once_resolved(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(400000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0);
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);

        // Pesanan pending aktif menahan arsip/penghapusan unit.
        $this->actingAs($admin)->delete(route('admin.cars.destroy', $car))
            ->assertSessionHas('error');
        $this->assertNull($car->fresh()->archived_at);

        // Setelah pesanan dikonfirmasi pun pengarsipan tetap ditolak sampai diselesaikan atau dipindah/dibatalkan.
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('confirm-hold.jpg'),
        ])->assertRedirect();
        $payment = $rental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->actingAs($admin)->delete(route('admin.cars.destroy', $car))
            ->assertSessionHas('error');
        $this->assertNull($car->fresh()->archived_at);

        // Setelah transaksi dibatalkan (dan memiliki riwayat transaksi), pengarsipan berhasil tanpa menghapus baris historis.
        $this->actingAs($admin)->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_RENTAL_FAULT,
            'cancellation_reason' => 'Unit akan dipensiunkan dari armada operasional.',
        ])->assertRedirect();

        $this->actingAs($admin)->delete(route('admin.cars.destroy', $car))
            ->assertRedirect(route('admin.cars.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($car->fresh()->archived_at);
    }

    public function test_expired_extensions_auto_release_with_refund_and_unblock_completion(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->subDays(2)->setTime(9, 0);
        $rental = Rental::forceCreate([
            'rental_code' => 'EXT-'.strtoupper(substr(uniqid(), -8)),
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
            'payment_bank_name' => 'Bank Awal Snapshot',
            'payment_account_number' => '111222333444',
            'payment_account_holder' => 'PT Snapshot Awal',
            'terms_accepted_at' => $pickupAt,
        ]);

        // Pelanggan mengajukan perpanjangan, admin menyetujui -> snapshot rekening disimpan ke perpanjangan.
        $extension = app(RentalExtensionService::class)->requestExtension($rental, $customer, 1, 'Tambah 1 hari');
        $extension = app(RentalExtensionService::class)->approveExtension($extension, $admin);

        $this->assertSame('Bank Awal Snapshot', $extension->payment_bank_name);
        $this->assertSame('111222333444', $extension->payment_account_number);
        $this->assertSame('PT Snapshot Awal', $extension->payment_account_holder);

        // Simulasikan pembayaran parsial masuk Rp 150.000, lalu tenggat pembayaran perpanjangan terlewati.
        $extension->forceFill([
            'paid_amount' => 150000,
            'verified_amount' => 150000,
            'payment_deadline_at' => CarbonImmutable::now('Asia/Jakarta')->subMinutes(10),
        ])->saveOrFail();

        $this->artisan('rentals:expire-unpaid')->assertSuccessful();

        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_EXPIRED, $extension->status);
        $this->assertSame(150000.0, (float) $extension->refund_amount);
        $this->assertSame(RentalExtension::REFUND_PENDING, $extension->refund_status);

        // Serah-terima dan pengembalian tetap dapat dicatat (tidak terblokir perpanjangan kedaluwarsa),
        // tetapi penutupan transaksi tetap tertahan sampai refund dana parsial perpanjangan direalisasikan.
        app(RentalOperationService::class)->confirmPickup(
            $rental->fresh(),
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
                'odometer_km' => 20000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('ext-pickup.jpg')
        );

        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $pickupAt->addDay()->format('Y-m-d\TH:i'),
            'odometer_km' => 20100,
            'fuel_level' => 'full',
            'has_damage' => 0,
            'condition_photo' => UploadedFile::fake()->image('ext-return.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 0,
            'damage_fee_amount' => 0,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertSessionHasErrors('completion');

        // Setelah admin merealisasikan refund perpanjangan kedaluwarsa, transaksi dapat ditutup menjadi completed.
        $this->actingAs($admin)->patch(route('admin.extensions.refund', $extension), [
            'transfer_amount' => 150000,
            'transferred_at' => CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-EXT-EXPIRED-01',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertRedirect(route('admin.bookings.show', $rental));

        $this->assertSame(Rental::STATUS_COMPLETED, $rental->fresh()->rental_status);
    }

    public function test_overpayment_auto_refunds_and_bank_snapshots_remain_immutable(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(2)->startOfHour();
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);

        // Kelebihan bayar pada sewa utama (tagihan Rp 500.000, transfer diverifikasi Rp 575.000 -> refund Rp 75.000).
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('main-overpay.jpg'),
        ])->assertRedirect();
        $payment = $rental->payments()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => 575000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(575000.0, (float) $rental->paid_amount);
        $this->assertSame(75000.0, (float) $rental->overpayment_amount);
        $this->assertSame(75000.0, (float) $rental->refund_amount);
        $this->assertSame(Rental::REFUND_PENDING, $rental->refund_status);
        $this->assertSame(75000.0, $rental->pendingRefundBalance());

        // Ubah rekening operasional pada pengaturan global; snapshot pada pesanan & perpanjangan lama harus tetap utuh.
        DB::table('rental_settings')->where('id', 1)->update([
            'bank_name' => 'Bank Baru Tagihan Tambahan',
            'account_number' => '999888777666',
            'account_holder' => 'PT Rekening Baru',
        ]);

        // Ajukan & setujui perpanjangan (snapshot memakai Bank Baru), lalu bayar berlebih Rp 600.000 untuk tagihan Rp 500.000.
        $extension = app(RentalExtensionService::class)->requestExtension($rental, $customer, 1, 'Tambah 1 hari');
        $extension = app(RentalExtensionService::class)->approveExtension($extension, $admin);
        $this->assertSame('Bank Awal Snapshot', $rental->fresh()->payment_bank_name);
        $this->assertSame('Bank Baru Tagihan Tambahan', $extension->payment_bank_name);

        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $extension), [
            'payment_proof' => UploadedFile::fake()->image('ext-overpay.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => 600000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $extension->refresh();
        $this->assertSame(RentalExtension::STATUS_COMPLETED, $extension->status);
        $this->assertSame(100000.0, (float) $extension->refund_amount);
        $this->assertSame(RentalExtension::REFUND_PENDING, $extension->refund_status);

        // Ubah lagi rekening operasional, lalu serah-terima, kembalikan, dan tetapkan biaya BBM Rp 100.000.
        DB::table('rental_settings')->where('id', 1)->update([
            'bank_name' => 'Bank Ketiga Tambahan',
            'account_number' => '555444333222',
            'account_holder' => 'PT Rekening Tambahan',
        ]);

        $rental->refresh();
        app(RentalOperationService::class)->confirmPickup(
            $rental,
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
                'odometer_km' => 15000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('overpay-pickup.jpg')
        );

        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $rental->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 15150,
            'fuel_level' => 'half',
            'has_damage' => 0,
            'condition_photo' => UploadedFile::fake()->image('overpay-return.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 100000,
            'fuel_fee_reason' => 'Pengisian ulang BBM kurang setengah tangki.',
            'damage_fee_amount' => 0,
        ])->assertRedirect();

        $rental->refresh();
        $this->assertSame('Bank Ketiga Tambahan', $rental->additional_charge_bank_name);
        $this->assertSame('555444333222', $rental->additional_charge_account_number);
        $this->assertSame('Bank Awal Snapshot', $rental->payment_bank_name);

        // Pelanggan membayar tagihan tambahan Rp 100.000 berlebih menjadi Rp 125.000 (+25.000 refund).
        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('charge-overpay.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.approve', $rental), [
            'verified_amount' => 125000,
            'payment_reference' => 'TRF-CHARGE-OVER-01',
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $rental->refresh();
        $this->assertSame(125000.0, (float) $rental->additional_charge_paid_amount);
        // Total refund aktif pada rental utama = 75.000 (sewa utama) + 25.000 (biaya tambahan) = 100.000.
        $this->assertSame(100000.0, $rental->pendingRefundBalance());

        // Penutupan transaksi ditolak sebelum seluruh kelebihan bayar (utama + perpanjangan) direalisasikan.
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertSessionHasErrors('completion');

        // Realisasikan refund sewa utama (Rp 100.000) & refund kelebihan perpanjangan (Rp 100.000).
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $rental), [
            'transfer_amount' => 100000,
            'transferred_at' => CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-MAIN-OVER-01',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.extensions.refund', $extension), [
            'transfer_amount' => 100000,
            'transferred_at' => CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-EXT-OVER-01',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertRedirect(route('admin.bookings.show', $rental));

        $this->assertSame(Rental::STATUS_COMPLETED, $rental->fresh()->rental_status);
        $this->assertSame(Rental::REFUND_PROCESSED, $rental->fresh()->refund_status);
        $this->assertSame(RentalExtension::REFUND_PROCESSED, $extension->fresh()->refund_status);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Review '.uniqid(),
            'email' => $roleName.'_rev_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Review '.uniqid(),
            'email' => 'cust_rev_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function createBookableCar(int $pricePerDay, ?CarType $carType = null): Car
    {
        $carType ??= CarType::firstOrCreate(['name' => 'SUV Review'], ['description' => 'Tipe uji review']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Toyota Innova Review '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' RV',
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
