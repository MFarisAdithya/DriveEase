<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Rental;
use App\Models\RentalCashFlow;
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
            'terms' => 'Ketentuan uji dasbor, riwayat, dan laporan keuangan berbasis kas.',
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

        $actionRental = $bookingService->createBooking($customer, $car->id, $pickupActionable, 1, true);

        $paidRental = $bookingService->createBooking($customer, $car->id, $pickupPaid, 1, true);
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $paidRental), [
            'payment_proof' => UploadedFile::fake()->image('paid-receipt.jpg'),
        ])->assertRedirect();
        $paidPayment = $paidRental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $paidPayment), [
            'verified_amount' => (float) $paidRental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $otherRental = $bookingService->createBooking($otherCustomer, $car->id, $pickupPaid->addDays(4), 1, true);

        // C-01: Dasbor pelanggan menampilkan status verifikasi, jumlah pesanan aktif/tindakan, dan pemberitahuan tindakan.
        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Akun Terverifikasi')
            ->assertSee('Pemberitahuan yang Membutuhkan Tindakan Anda')
            ->assertSee($actionRental->rental_code)
            ->assertDontSee($otherRental->rental_code);

        // C-11: bookmark lama mengarah ke daftar pada Beranda, dengan filter
        // yang sama. Pemberitahuan di atas daftar tetap mencakup semua pesanan.
        $filters = [
            'search' => $paidRental->rental_code,
            'rental_status' => Rental::STATUS_CONFIRMED,
            'date_from' => $pickupPaid->toDateString(),
            'date_to' => $pickupPaid->toDateString(),
        ];
        $this->get(route('customer.bookings.index', $filters))
            ->assertRedirect(route('customer.dashboard', $filters).'#pesanan');

        $this->get(route('customer.dashboard', $filters))
            ->assertOk()
            ->assertSee('Bukti Transaksi Tersedia')
            ->assertViewHas('bookings', fn ($bookings): bool => $bookings->pluck('id')->all() === [$paidRental->id])
            ->assertDontSee($otherRental->rental_code);

        // Bukti transaksi terkonfirmasi tampil pada detail pesanan lunas milik pelanggan.
        $this->actingAs($customer)->get(route('customer.bookings.show', $paidRental))
            ->assertOk()
            ->assertSee('Bukti Transaksi Terkonfirmasi')
            ->assertDontSee($paidRental->payment_account_number);

        // Pelanggan lain ditolak (403) saat mencoba membuka transaksi yang bukan miliknya.
        $this->actingAs($customer)->get(route('customer.bookings.show', $otherRental))
            ->assertForbidden();
    }

    public function test_cash_basis_cross_month_reporting_matches_admin_dashboard_without_double_counting(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer('Penyewa Basis Kas');
        $car = $this->createBookableCar(500000);
        $carRefundPending = $this->createBookableCar(300000);
        $carLegacy = $this->createBookableCar(250000);

        $nowWib = CarbonImmutable::now('Asia/Jakarta');
        $monthBStart = $nowWib->startOfMonth(); // Bulan berjalan (Bulan B) -> harus cocok dengan A-01 dashboard
        $monthAEnd = $monthBStart->subDay(); // Bulan sebelumnya (Bulan A)
        $monthCStart = $monthBStart->addMonth(); // Bulan berikutnya (Bulan C)

        $receivedInMonthA = $monthAEnd->setTime(15, 0);
        $receivedInMonthBMain = $monthBStart->addDays(2)->setTime(10, 0);
        $receivedInMonthBExt = $monthBStart->addDays(3)->setTime(11, 0);
        $receivedInMonthBChargePartial = $monthBStart->addDays(5)->setTime(13, 0);
        $receivedInMonthCChargeFinal = $monthCStart->addDays(2)->setTime(14, 0);
        $refundInMonthC = $monthCStart->addDays(4)->setTime(16, 0);

        // Pesanan dibuat & dijadwalkan di Bulan B (pickup_at masa mendatang dalam Bulan B), namun uang masuk tersebar lintas Bulan A, B, C.
        $pickupMonthB = $nowWib->addHours(4)->startOfHour();
        $rental = Rental::forceCreate([
            'rental_code' => 'KAS-'.strtoupper(substr(uniqid(), -8)),
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'pickup_at' => $pickupMonthB,
            'return_at' => $pickupMonthB->addDay(),
            'start_date' => $pickupMonthB->toDateString(),
            'end_date' => $pickupMonthB->addDay()->toDateString(),
            'total_days' => 1,
            'daily_price' => 500000,
            'total_amount' => 500000,
            'paid_amount' => 0,
            'payment_deadline_at' => $nowWib->addHours(2),
            'payment_status' => Rental::PAYMENT_UNPAID,
            'rental_status' => Rental::STATUS_PENDING,
            'booking_channel' => Rental::CHANNEL_ONLINE,
            'payment_bank_name' => 'Bank Laporan DriveEase',
            'payment_account_number' => '123456789012',
            'payment_account_holder' => 'PT DriveEase Laporan',
            'terms_accepted_at' => $monthAEnd->setTime(12, 0),
        ]);

        // Unggah bukti ke-1 lalu ditolak dengan penerimaan parsial Rp 200.000 pada tanggal kas Bulan A.
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('kas-m1.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $pay1 = $rental->payments()->firstOrFail();

        // Validasi menolak pencatatan dana masuk sebagian tanpa tanggal aktual terima (received_at).
        $this->actingAs($admin)->patch(route('admin.payments.reject', $pay1), [
            'rejection_reason' => 'Dana masuk masih kurang Rp 300.000.',
            'received_amount' => 200000,
        ])->assertSessionHasErrors('received_at');

        $this->actingAs($admin)->patch(route('admin.payments.reject', $pay1), [
            'rejection_reason' => 'Dana masuk sebagian pada akhir bulan lalu.',
            'received_amount' => 200000,
            'received_at' => $receivedInMonthA->format('Y-m-d\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Pelanggan unggah pelunasan; admin verifikasi Rp 350.000 pada tanggal kas Bulan B (+50.000 overpayment refund pending).
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('kas-m2.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $pay2 = $rental->payments()->firstOrFail();

        // Validasi menolak persetujuan pembayaran tanpa received_at.
        $this->actingAs($admin)->patch(route('admin.payments.approve', $pay2), [
            'verified_amount' => 350000,
        ])->assertSessionHasErrors('received_at');

        $this->actingAs($admin)->patch(route('admin.payments.approve', $pay2), [
            'verified_amount' => 350000,
            'received_at' => $receivedInMonthBMain->format('Y-m-d\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Perpanjangan Rp 500.000 diverifikasi pada tanggal kas Bulan B -> dicatat sekali saja sebagai extension_payment.
        $extensionService = app(RentalExtensionService::class);
        $ext = $extensionService->requestExtension($rental->fresh(), $customer, 1, 'Tambah 1 hari');
        $ext = $extensionService->approveExtension($ext, $admin);
        $this->actingAs($customer)->post(route('customer.extensions.payment.store', $ext), [
            'payment_proof' => UploadedFile::fake()->image('kas-ext.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $ext), [
            'verified_amount' => 500000,
            'received_at' => $receivedInMonthBExt->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        // Serah-terima & kembali + tagihan tambahan Rp 100.000:
        // sebagian Rp 40.000 diterima pada Bulan B, sisa Rp 60.000 diterima pada Bulan C.
        app(RentalOperationService::class)->confirmPickup(
            $rental->fresh(),
            $staff,
            [
                'identity_checked' => true,
                'actual_pickup_at' => $pickupMonthB->format('Y-m-d\TH:i'),
                'odometer_km' => 1000,
                'fuel_level' => 'full',
            ],
            UploadedFile::fake()->image('kas-pickup.jpg')
        );
        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $rental->fresh()->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 1200,
            'fuel_level' => 'half',
            'has_damage' => 0,
            'condition_photo' => UploadedFile::fake()->image('kas-return.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 100000,
            'fuel_fee_reason' => 'Kekurangan BBM pengembalian.',
            'damage_fee_amount' => 0,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('kas-chg1.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.reject', $rental), [
            'rejection_reason' => 'Baru masuk Rp 40.000 pada Bulan B.',
            'received_amount' => 40000,
            'received_at' => $receivedInMonthBChargePartial->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('kas-chg2.jpg'),
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.approve', $rental), [
            'verified_amount' => 60000,
            'payment_reference' => 'KAS-CHG-60K',
            'received_at' => $receivedInMonthCChargeFinal->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        // Realisasikan refund kelebihan bayar Rp 50.000 pada Bulan C.
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $rental), [
            'transfer_amount' => 50000,
            'transferred_at' => $refundInMonthC->format('Y-m-d\TH:i'),
            'refund_reference' => 'KAS-REF-50K',
        ])->assertRedirect();

        // Tambahkan 1 pesanan lain yang memiliki kewajiban refund tertunda Rp 300.000 (belum ditransfer -> terpisah sebagai kewajiban).
        $pickupPendingRefund = $monthBStart->addDays(8)->setTime(10, 0);
        $rentalPending = Rental::forceCreate([
            'rental_code' => 'LIAB-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => $customer->id,
            'car_id' => $carRefundPending->id,
            'pickup_at' => $pickupPendingRefund,
            'return_at' => $pickupPendingRefund->addDay(),
            'start_date' => $pickupPendingRefund->toDateString(),
            'end_date' => $pickupPendingRefund->addDay()->toDateString(),
            'total_days' => 1,
            'daily_price' => 300000,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'payment_status' => Rental::PAYMENT_PAID,
            'rental_status' => Rental::STATUS_CONFIRMED,
            'booking_channel' => Rental::CHANNEL_WALK_IN,
            'terms_accepted_at' => $monthBStart,
        ]);
        $this->actingAs($admin)->patch(route('admin.bookings.cancel-with-refund', $rentalPending), [
            'cancellation_type' => Rental::CANCEL_RENTAL_FAULT,
            'cancellation_reason' => 'Refund belum ditransfer, harus tampil sebagai kewajiban terpisah.',
        ])->assertRedirect();

        // Tambahkan 1 transaksi historis pra-ledger tanpa tanggal penerimaan aktual -> TIDAK boleh dikarang masuk periode mana pun.
        Rental::forceCreate([
            'rental_code' => 'HIST-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => $customer->id,
            'car_id' => $carLegacy->id,
            'pickup_at' => $monthBStart->addDays(1)->setTime(8, 0),
            'return_at' => $monthBStart->addDays(2)->setTime(8, 0),
            'start_date' => $monthBStart->addDays(1)->toDateString(),
            'end_date' => $monthBStart->addDays(2)->toDateString(),
            'total_days' => 1,
            'daily_price' => 250000,
            'total_amount' => 250000,
            'paid_amount' => 250000,
            'payment_status' => Rental::PAYMENT_PAID,
            'rental_status' => Rental::STATUS_COMPLETED,
            'booking_channel' => Rental::CHANNEL_ONLINE,
            'terms_accepted_at' => $monthAEnd,
        ]);

        $reportService = app(RentalReportService::class);

        // Laporan Bulan A: hanya parsial utama Rp 200.000 (bukan jadwal ambil Bulan B!).
        $reportA = $reportService->buildPeriodReport($monthAEnd->startOfMonth(), $monthAEnd->endOfMonth());
        $this->assertEquals(200000.0, $reportA['summary']['gross_receipts']);
        $this->assertEquals(0.0, $reportA['summary']['processed_refunds']);
        $this->assertEquals(200000.0, $reportA['summary']['net_receipts']);

        // Laporan Bulan B (bulan berjalan): 350.000 (sisa+overpay) + 500.000 (perpanjangan) + 40.000 (parsial BBM) = 890.000.
        // Kewajiban refund tertunda Rp 300.000 TIDAK mengurangi kas bersih Bulan B.
        $reportB = $reportService->buildPeriodReport($monthBStart, $monthBStart->endOfMonth());
        $this->assertEquals(890000.0, $reportB['summary']['gross_receipts']);
        $this->assertEquals(0.0, $reportB['summary']['processed_refunds']);
        $this->assertEquals(890000.0, $reportB['summary']['net_receipts']);
        $this->assertGreaterThanOrEqual(300000.0, $reportB['summary']['pending_refunds_liability']);
        $this->assertGreaterThanOrEqual(1, $reportB['summary']['unrecorded_legacy_count']);

        // Pastikan laporan A-12 & dasbor A-01 untuk bulan berjalan menghasilkan angka kas yang identik.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('monthSummary', fn (array $summary): bool => $summary['gross_receipts'] === $reportB['summary']['gross_receipts']
                && $summary['processed_refunds'] === $reportB['summary']['processed_refunds']
                && $summary['net_receipts'] === $reportB['summary']['net_receipts']
                && $summary['pending_refunds_liability'] === $reportB['summary']['pending_refunds_liability']);

        $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => $monthBStart->toDateString(),
            'date_to' => $monthBStart->endOfMonth()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Penerimaan Kas Kotor')
            ->assertSee('Kewajiban Refund Tertunda')
            ->assertSee('Catatan Data Historis Pra-Buku Kas')
            ->assertSee($rental->rental_code);

        // Laporan Bulan C: pelunasan tambahan Rp 60.000 dan realisasi refund Rp 50.000 -> bersih Rp 10.000.
        $reportC = $reportService->buildPeriodReport($monthCStart, $monthCStart->endOfMonth());
        $this->assertEquals(60000.0, $reportC['summary']['gross_receipts']);
        $this->assertEquals(50000.0, $reportC['summary']['processed_refunds']);
        $this->assertEquals(10000.0, $reportC['summary']['net_receipts']);

        // Pastikan total buku kas seluruh entri milik $rental tepat 1.150.000 masuk dan 50.000 keluar (tanpa dobel).
        $rentalFlows = RentalCashFlow::query()->where('rental_id', $rental->id)->get();
        $this->assertEquals(1150000.0, (float) $rentalFlows->where('direction', RentalCashFlow::DIRECTION_INFLOW)->sum('amount'));
        $this->assertEquals(50000.0, (float) $rentalFlows->where('direction', RentalCashFlow::DIRECTION_OUTFLOW)->sum('amount'));

        // A-11: Filter riwayat transaksi admin berdasarkan kode, status sewa, status bayar, dan rentang jadwal.
        $this->actingAs($admin)->get(route('admin.bookings.index', [
            'search' => $rentalPending->rental_code,
            'rental_status' => Rental::STATUS_CANCELLED,
            'payment_status' => Rental::PAYMENT_PAID,
            'date_from' => $pickupPendingRefund->toDateString(),
            'date_to' => $pickupPendingRefund->toDateString(),
        ]))
            ->assertOk()
            ->assertSee($rentalPending->rental_code)
            ->assertDontSee($rental->rental_code);

        // Validasi tanggal terbalik ditolak dan non-admin mendapat 403.
        $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => '2026-12-10',
            'date_to' => '2026-12-01',
        ]))->assertSessionHasErrors('date_to');
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
