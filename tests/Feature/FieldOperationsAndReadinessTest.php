<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use App\Services\RentalFinancialService;
use App\Services\RentalPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FieldOperationsAndReadinessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Lapangan',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Uji Lapangan No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji operasional resmi.',
            'bank_name' => 'Bank Uji DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Uji',
        ], ['id']);
    }

    public function test_late_penalty_calculation_follows_skpl_tiers_and_decision_a3(): void
    {
        $financialService = app(RentalFinancialService::class);
        $agreedReturnAt = CarbonImmutable::create(2026, 12, 10, 10, 0, 0, 'Asia/Jakarta');

        $rental = new Rental([
            'return_at' => $agreedReturnAt,
            'daily_price' => 500000,
        ]);

        // <= 30 menit -> 0 denda
        $grace = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addMinutes(30));
        $this->assertEquals(0.0, $grace['late_fee_amount']);

        // 31 menit -> ceil(1 jam) * 10% = 50.000
        $tier31m = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addMinutes(31));
        $this->assertSame(1, $tier31m['billable_hours']);
        $this->assertEquals(50000.0, $tier31m['late_fee_amount']);

        // 5 jam tepat -> 5 * 10% = 250.000
        $tier5h = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addHours(5));
        $this->assertSame(5, $tier5h['billable_hours']);
        $this->assertEquals(250000.0, $tier5h['late_fee_amount']);

        // 5 jam 1 menit -> 1 hari penuh = 500.000
        $tier5h1m = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addHours(5)->addMinute());
        $this->assertSame(1, $tier5h1m['full_days']);
        $this->assertEquals(500000.0, $tier5h1m['late_fee_amount']);

        // > 24 jam (A3: toleransi 30 menit hanya sekali di awal; 24 jam 20 menit = 100% + 10% = 110% = 550.000)
        $tier24h20m = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addHours(24)->addMinutes(20));
        $this->assertSame(1, $tier24h20m['full_days']);
        $this->assertSame(1, $tier24h20m['billable_hours']);
        $this->assertEquals(550000.0, $tier24h20m['late_fee_amount']);

        // 30 jam 5 menit = 1 hari + > 5 jam = 2 hari penuh = 1.000.000
        $tier30h5m = $financialService->calculateLatePenalty($rental, $agreedReturnAt->addHours(30)->addMinutes(5));
        $this->assertSame(2, $tier30h5m['full_days']);
        $this->assertEquals(1000000.0, $tier30h5m['late_fee_amount']);
    }

    public function test_staff_dashboard_schedules_privacy_and_handover_preventing_duplicates(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(4)->startOfHour();
        $rental = $bookingService->createBooking($customer, $car->id, $pickupAt, 2, true);
        $payment = $paymentService->submitProof($rental, $customer, UploadedFile::fake()->image('bayar.jpg'));
        $paymentService->approvePayment($payment, $admin, (float) $rental->total_amount, now('Asia/Jakarta'));
        $rental->refresh();

        // Dasbor staf menampilkan jadwal pengambilan terkonfirmasi & aksi persiapan (S-01).
        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee($rental->rental_code);

        // Jadwal operasional staf menampilkan detail penyewa terverifikasi & status lunas,
        // namun dilarang mengakses KTP/SIM atau bukti bayar (S-02, NFR-01).
        $this->actingAs($staff)->get(route('staff.schedules.index'))
            ->assertOk()
            ->assertSee($rental->rental_code)
            ->assertSee('Terverifikasi')
            ->assertSee('Lunas');

        $this->actingAs($staff)->get(route('staff.schedules.show', $rental))
            ->assertOk()
            ->assertDontSee('3174012345678901');

        $this->actingAs($staff)->get(route('documents.identity.show', ['user' => $customer, 'type' => 'ktp']))->assertForbidden();
        $this->actingAs($staff)->get(route('documents.payment.show', $payment))->assertForbidden();

        // Pelanggan dilarang mengakses endpoint staf (403).
        $this->actingAs($customer)->get(route('staff.schedules.index'))->assertForbidden();

        // Serah-terima harus gagal apabila kendaraan ditahan / belum siap (needs_preparation) (SYS-08).
        $car->forceFill(['readiness_status' => Car::READINESS_NEEDS_PREPARATION])->save();
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickupAt->addMinutes(15)->format('Y-m-d\TH:i'),
            'odometer_km' => 10500,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('pickup-blocked.jpg'),
        ])->assertSessionHasErrors('handover');

        // Staf menandai kendaraan layak siap -> serah-terima berhasil (S-03, SYS-08).
        $this->actingAs($staff)->patch(route('staff.cars.mark-ready', $car), [
            'readiness_notes' => 'Unit bersih, tekanan ban & surat lengkap.',
        ])->assertRedirect();

        $agreedReturn = $rental->return_at->copy()->timezone('Asia/Jakarta');
        $actualPickup = $pickupAt->addMinutes(30);

        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $actualPickup->format('Y-m-d\TH:i'),
            'odometer_km' => 10500,
            'fuel_level' => 'full',
            'condition_notes' => 'Bodi mulus, ban serep ada.',
            'condition_photo' => UploadedFile::fake()->image('pickup-ok.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental));

        $rental->refresh();
        $car->refresh();
        $this->assertSame(Rental::STATUS_PICKED_UP, $rental->rental_status);
        $this->assertTrue($rental->actual_pickup_at->timezone('Asia/Jakarta')->equalTo($actualPickup));
        // Keterlambatan pengambilan TIDAK menggeser return_at (SYS-03).
        $this->assertTrue($rental->return_at->timezone('Asia/Jakarta')->equalTo($agreedReturn));
        $this->assertSame('rented', $car->status);
        $this->assertSame(Car::READINESS_IN_USE, $car->readiness_status);

        $pickupInspection = $rental->pickupInspection()->firstOrFail();
        $photoUrl = route('documents.inspection-photo.show', $pickupInspection);
        $this->actingAs($staff)->get($photoUrl)->assertOk();
        $this->actingAs($admin)->get($photoUrl)->assertOk();
        $this->actingAs($customer)->get($photoUrl)->assertOk();
        $this->actingAs($otherCustomer)->get($photoUrl)->assertForbidden();

        // Penyerahan ulang untuk pesanan yang sama harus ditolak (S-03, SYS-08, NFR-04).
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $actualPickup->addMinutes(10)->format('Y-m-d\TH:i'),
            'odometer_km' => 10510,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('pickup-dup.jpg'),
        ])->assertSessionHasErrors('handover');
    }

    public function test_return_inspection_odometer_guard_damage_readiness_and_impacted_next_booking(): void
    {
        $bookingService = app(RentalBookingService::class);
        $paymentService = app(RentalPaymentService::class);
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer1 = $this->createVerifiedCustomer();
        $customer2 = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(600000);

        $pickupAt1 = CarbonImmutable::now('Asia/Jakarta')->addHours(2)->startOfHour();
        $rental1 = $bookingService->createBooking($customer1, $car->id, $pickupAt1, 1, true);
        $pay1 = $paymentService->submitProof($rental1, $customer1, UploadedFile::fake()->image('p1.jpg'));
        $paymentService->approvePayment($pay1, $admin, (float) $rental1->total_amount, now('Asia/Jakarta'));
        $rental1->refresh();

        // Pesanan ke-2 terpasang tepat 65 menit setelah batas kembali pesanan ke-1.
        $pickupAt2 = $rental1->return_at->copy()->timezone('Asia/Jakarta')->addMinutes(65);
        $rental2 = $bookingService->createBooking($customer2, $car->id, $pickupAt2, 1, true);
        $pay2 = $paymentService->submitProof($rental2, $customer2, UploadedFile::fake()->image('p2.jpg'));
        $paymentService->approvePayment($pay2, $admin, (float) $rental2->total_amount, now('Asia/Jakarta'));

        // Staf melakukan serah-terima pesanan ke-1.
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental1), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickupAt1->format('Y-m-d\TH:i'),
            'odometer_km' => 25000,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('awal.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental1));

        $rental1->refresh();

        // Kilometer akhir lebih rendah dari kilometer awal wajib ditolak (S-04).
        $this->actingAs($staff)->post(route('staff.schedules.return', $rental1), [
            'actual_return_at' => $rental1->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 24900,
            'fuel_level' => 'half',
            'condition_photo' => UploadedFile::fake()->image('akhir-bad.jpg'),
        ])->assertSessionHasErrors('odometer_km');

        // Kendaraan kembali terlambat 90 menit (denda 2 jam * 10% = 120.000) dengan kerusakan bumper (S-04, S-05, SYS-10).
        $lateReturnAt = $rental1->return_at->copy()->timezone('Asia/Jakarta')->addMinutes(90);
        $this->actingAs($staff)->post(route('staff.schedules.return', $rental1), [
            'actual_return_at' => $lateReturnAt->format('Y-m-d\TH:i'),
            'odometer_km' => 25420,
            'fuel_level' => 'half',
            'has_damage' => 1,
            'damage_description' => 'Bumper depan retak akibat benturan parkir.',
            'condition_photo' => UploadedFile::fake()->image('akhir-damage.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental1));

        $rental1->refresh();
        $car->refresh();
        $this->assertSame(Rental::STATUS_RETURNED, $rental1->rental_status);
        $this->assertSame(90, $rental1->late_minutes);
        $this->assertEquals(120000.0, (float) $rental1->late_fee_amount);
        $this->assertSame('maintenance', $car->status);
        $this->assertSame(Car::READINESS_NEEDS_MAINTENANCE, $car->readiness_status);

        // Pesanan ke-2 otomatis terdeteksi terdampak & tampil di filter affected_bookings admin (SYS-10).
        $this->assertDatabaseHas('rental_histories', [
            'rental_id' => $rental2->id,
            'action' => 'next_booking_impacted',
        ]);

        $this->actingAs($admin)->get(route('admin.bookings.index', ['filter' => 'affected_bookings']))
            ->assertOk()
            ->assertSee($rental2->rental_code);

        // Pelanggan ke-1 maupun admin melihat perbandingan hasil inspeksi di detail pesanan (C-07).
        $this->actingAs($customer1)->get(route('customer.bookings.show', $rental1))
            ->assertOk()
            ->assertSee('25.420 km')
            ->assertSee('Bumper depan retak');

        // Staf atau admin dapat menandai unit layak setelah perbaikan selesai (A4, S-05).
        $this->actingAs($admin)->patch(route('admin.cars.mark-ready', $car), [
            'readiness_notes' => 'Bumper sudah diganti dan unit lulus uji jalan.',
        ])->assertRedirect();

        $car->refresh();
        $this->assertSame('available', $car->status);
        $this->assertSame(Car::READINESS_READY, $car->readiness_status);
        $this->assertNull($car->maintenance_reason);

        // Staf tetap bisa melaporkan kerusakan baru kapan saja melalui kesiapan armada (S-05).
        $this->actingAs($staff)->patch(route('staff.cars.report-damage', $car), [
            'maintenance_reason' => 'Lampu depan sebelah kanan redup saat pengecekan malam.',
        ])->assertRedirect();

        $car->refresh();
        $this->assertSame('maintenance', $car->status);
        $this->assertSame(Car::READINESS_NEEDS_MAINTENANCE, $car->readiness_status);
        $this->assertInstanceOf(Inspection::class, $rental1->returnInspection);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Ops '.uniqid(),
            'email' => $roleName.'_ops_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Ops '.uniqid(),
            'email' => 'cust_ops_'.uniqid().'@driveease.test',
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
        $carType = CarType::firstOrCreate(['name' => 'MPV Operasional'], ['description' => 'Tipe uji operasional']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Toyota Innova Ops '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' OP',
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
