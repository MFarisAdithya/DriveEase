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
use App\Services\RentalReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardHistoryAndReportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Laporan',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Laporan No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji dasbor, riwayat, dan laporan keuangan.',
            'bank_name' => 'Bank Laporan DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Laporan',
        ], ['id']);
    }

    public function test_customer_dashboard_and_history_enforce_ownership_filters_and_confirmed_receipt(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer('Pelanggan Laporan Satu');
        $otherCustomer = $this->createVerifiedCustomer('Pelanggan Rahasia Dua');
        $car = $this->createBookableCar(450000);

        $bookingService = app(RentalBookingService::class);
        $pickupActionable = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(9, 0);
        $pickupPaid = CarbonImmutable::now('Asia/Jakarta')->addDays(6)->setTime(10, 0);

        // Pesanan 1 masih unpaid (membutuhkan unggah bukti pembayaran di dasbor pelanggan).
        $actionRental = $bookingService->createBooking($customer, $car->id, $pickupActionable, 1, true);

        // Pesanan 2 dibayar lunas dan diverifikasi admin -> punya Bukti Transaksi Terkonfirmasi.
        $paidRental = $bookingService->createBooking($customer, $car->id, $pickupPaid, 1, true);
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $paidRental), [
            'payment_proof' => UploadedFile::fake()->image('paid-receipt.jpg'),
        ])->assertRedirect();
        $paidPayment = $paidRental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $paidPayment), [
            'verified_amount' => (float) $paidRental->total_amount,
        ])->assertRedirect();

        // Pesanan 3 milik pelanggan lain tidak boleh tampil di dasbor/riwayat maupun diakses detailnya.
        $otherRental = $bookingService->createBooking($otherCustomer, $car->id, $pickupPaid->addDays(4), 1, true);

        // C-01: Dasbor pelanggan menampilkan status verifikasi, jumlah pesanan aktif/tindakan, dan pemberitahuan tindakan.
        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Akun Terverifikasi')
            ->assertSee('Pemberitahuan yang Membutuhkan Tindakan Anda')
            ->assertSee($actionRental->rental_code)
            ->assertDontSee($otherRental->rental_code);

        // C-11: Filter riwayat pribadi berdasarkan kode, status, dan periode WIB.
        $this->actingAs($customer)->get(route('customer.bookings.index', [
            'search' => $paidRental->rental_code,
            'rental_status' => Rental::STATUS_CONFIRMED,
            'date_from' => $pickupPaid->toDateString(),
            'date_to' => $pickupPaid->toDateString(),
        ]))
            ->assertOk()
            ->assertSee($paidRental->rental_code)
            ->assertSee('Bukti Transaksi Tersedia')
            ->assertDontSee($actionRental->rental_code)
            ->assertDontSee($otherRental->rental_code);

        // Bukti transaksi terkonfirmasi tampil pada detail pesanan lunas milik pelanggan.
        $this->actingAs($customer)->get(route('customer.bookings.show', $paidRental))
            ->assertOk()
            ->assertSee('Bukti Transaksi Terkonfirmasi')
            ->assertSee($paidRental->payment_account_number);

        // Pelanggan lain ditolak (403) saat mencoba membuka transaksi yang bukan miliknya.
        $this->actingAs($customer)->get(route('customer.bookings.show', $otherRental))
            ->assertForbidden();
    }

    public function test_admin_dashboard_history_filters_and_period_report_reconcile_without_double_counting(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer('Penyewa Laporan');
        $car1 = $this->createBookableCar(500000);
        $car2 = $this->createBookableCar(400000);
        $car3 = $this->createBookableCar(350000);

        $nowWib = CarbonImmutable::now('Asia/Jakarta');
        $bookingService = app(RentalBookingService::class);
        $extensionService = app(RentalExtensionService::class);

        // 1. Transaksi selesai dengan perpanjangan aktif + kelebihan bayar + tagihan tambahan lunas.
        // Sewa utama tagihan 500rb, bayar 550rb (kelebihan 50rb); perpanjangan 500rb dibayar pas;
        // BBM 100rb dibayar pas; refund kelebihan 50rb direalisasikan.
        // Harapan: penerimaan kotor = 1.050.000 + 100.000 = 1.150.000 (tidak dobel hitung perpanjangan completed),
        // refund terealisasi = 50.000, pending refund = 0, penerimaan bersih = 1.100.000.
        $pickup1 = $nowWib->startOfMonth()->addHours(10);
        $rental1 = Rental::forceCreate([
            'rental_code' => 'RPT-A-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => $customer->id,
            'car_id' => $car1->id,
            'pickup_at' => $pickup1,
            'return_at' => $pickup1->addDay(),
            'start_date' => $pickup1->toDateString(),
            'end_date' => $pickup1->addDay()->toDateString(),
            'total_days' => 1,
            'daily_price' => 500000,
            'total_amount' => 500000,
            'paid_amount' => 0,
            'payment_status' => Rental::PAYMENT_UNPAID,
            'rental_status' => Rental::STATUS_PENDING,
            'booking_channel' => Rental::CHANNEL_ONLINE,
            'payment_bank_name' => 'Bank Laporan DriveEase',
            'payment_account_number' => '123456789012',
            'payment_account_holder' => 'PT DriveEase Laporan',
            'terms_accepted_at' => $pickup1,
        ]);

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental1), [
            'payment_proof' => UploadedFile::fake()->image('rpt-main.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $rental1->payments()->firstOrFail()), [
            'verified_amount' => 550000,
        ])->assertRedirect();

        $ext1 = $extensionService->requestExtension($rental1->fresh(), $customer, 1, 'Tambah 1 hari');
        $ext1 = $extensionService->approveExtension($ext1, $admin);
        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $ext1), [
            'payment_proof' => UploadedFile::fake()->image('rpt-ext.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $ext1), [
            'verified_amount' => 500000,
        ])->assertRedirect();

        // Tambahkan juga perpanjangan ke-2 yang dibatalkan/kedaluwarsa dengan dana parsial 75rb belum direfund.
        RentalExtension::forceCreate([
            'rental_id' => $rental1->id,
            'requested_by' => $customer->id,
            'additional_days' => 1,
            'previous_return_at' => $rental1->fresh()->return_at,
            'proposed_return_at' => $rental1->fresh()->return_at->addDay(),
            'daily_price' => 500000,
            'additional_amount' => 500000,
            'paid_amount' => 75000,
            'verified_amount' => 75000,
            'status' => RentalExtension::STATUS_EXPIRED,
            'refund_amount' => 75000,
            'refund_status' => RentalExtension::REFUND_PENDING,
        ]);

        app(RentalOperationService::class)->confirmPickup(
            $rental1->fresh(),
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickup1->format('Y-m-d\TH:i'),
                'odometer_km' => 1000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('rpt-pickup.jpg')
        );

        $this->actingAs($staff)->post(route('staff.schedules.return', $rental1), [
            'actual_return_at' => $rental1->fresh()->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 1200,
            'fuel_level' => 'half',
            'has_damage' => 0,
            'condition_photo' => UploadedFile::fake()->image('rpt-return.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental1), [
            'fuel_fee_amount' => 100000,
            'fuel_fee_reason' => 'Kekurangan BBM pengembalian.',
            'damage_fee_amount' => 0,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental1), [
            'additional_charge_proof' => UploadedFile::fake()->image('rpt-charge.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.approve', $rental1), [
            'verified_amount' => 100000,
            'payment_reference' => 'RPT-FUEL-01',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.refund', $rental1), [
            'transfer_amount' => 50000,
            'transferred_at' => $nowWib->format('Y-m-d\TH:i'),
            'refund_reference' => 'RPT-REF-MAIN-01',
        ])->assertRedirect();

        // 2. Transaksi bulan yang sama yang dibatalkan dengan refund 75% terealisasi (400rb masuk, 300rb refund).
        $pickup2 = $nowWib->startOfMonth()->addDays(2)->setTime(11, 0);
        $rental2 = Rental::forceCreate([
            'rental_code' => 'RPT-B-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => $customer->id,
            'car_id' => $car2->id,
            'pickup_at' => $pickup2,
            'return_at' => $pickup2->addDay(),
            'start_date' => $pickup2->toDateString(),
            'end_date' => $pickup2->addDay()->toDateString(),
            'total_days' => 1,
            'daily_price' => 400000,
            'total_amount' => 400000,
            'paid_amount' => 400000,
            'payment_status' => Rental::PAYMENT_PAID,
            'rental_status' => Rental::STATUS_CONFIRMED,
            'booking_channel' => Rental::CHANNEL_WALK_IN,
            'payment_bank_name' => 'Bank Laporan DriveEase',
            'payment_account_number' => '123456789012',
            'payment_account_holder' => 'PT DriveEase Laporan',
            'terms_accepted_at' => $pickup2,
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.cancel-with-refund', $rental2), [
            'cancellation_type' => Rental::CANCEL_RENTAL_FAULT,
            'cancellation_reason' => 'Uji rekap pembatalan bulan berjalan.',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.bookings.refund', $rental2), [
            'transfer_amount' => 400000,
            'transferred_at' => $nowWib->format('Y-m-d\TH:i'),
            'refund_reference' => 'RPT-REF-CANCEL-02',
        ])->assertRedirect();

        // 3. Transaksi bulan depan agar tidak ikut terhitung dalam filter bulan berjalan.
        $nextMonthPickup = $nowWib->addMonth()->startOfMonth()->addDays(2)->setTime(10, 0);
        $futureRental = $bookingService->createBooking($customer, $car3->id, $nextMonthPickup, 1, true);

        // Verifikasi angka layanan & dasbor admin (A-01).
        $monthReport = app(RentalReportService::class)->buildPeriodReport($nowWib->startOfMonth(), $nowWib->endOfMonth());
        $expectedSummary = $monthReport['summary'];

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('monthSummary', fn ($summary) => $summary['gross_receipts'] === $expectedSummary['gross_receipts']
                && $summary['net_receipts'] === $expectedSummary['net_receipts'])
            ->assertSee($rental1->rental_code);

        // A-11: Filter riwayat transaksi admin berdasarkan kode, periode WIB, rental_status, dan payment_status.
        $this->actingAs($admin)->get(route('admin.bookings.index', [
            'search' => $rental2->rental_code,
            'rental_status' => Rental::STATUS_CANCELLED,
            'payment_status' => Rental::PAYMENT_PAID,
            'date_from' => $pickup2->toDateString(),
            'date_to' => $pickup2->toDateString(),
        ]))
            ->assertOk()
            ->assertSee($rental2->rental_code)
            ->assertDontSee($rental1->rental_code)
            ->assertDontSee($futureRental->rental_code);

        // A-12: Laporan periode /admin/reports menampilkan total sesuai rincian transaksi & menolak rentang tanggal terbalik.
        $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => $nowWib->startOfMonth()->toDateString(),
            'date_to' => $nowWib->endOfMonth()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee($rental1->rental_code)
            ->assertSee($rental2->rental_code)
            ->assertDontSee($futureRental->rental_code)
            ->assertViewHas('rows', function ($rows) use ($expectedSummary): bool {
                return round((float) $rows->sum('gross_receipts'), 2) === $expectedSummary['gross_receipts']
                    && round((float) $rows->sum('processed_refunds'), 2) === $expectedSummary['processed_refunds']
                    && round((float) $rows->sum('pending_refunds'), 2) === $expectedSummary['pending_refunds']
                    && round((float) $rows->sum('net_receipts'), 2) === $expectedSummary['net_receipts'];
            });

        $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => '2026-12-10',
            'date_to' => '2026-12-01',
        ]))->assertSessionHasErrors('date_to');

        // Staf & pelanggan dilarang mengakses laporan keuangan admin (403).
        $this->actingAs($staff)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.reports.index'))->assertForbidden();
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Report '.uniqid(),
            'email' => $roleName.'_rpt_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(string $name): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => $name.' '.substr(uniqid(), -4),
            'email' => 'cust_rpt_'.uniqid().'@driveease.test',
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
        $carType = CarType::firstOrCreate(['name' => 'MPV Report'], ['description' => 'Tipe uji laporan']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Toyota Veloz Report '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' RP',
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
