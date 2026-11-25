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
use App\Services\RentalPaymentService;
use App\Services\RentalSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AdditionalChargesAndCompletionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Penyelesaian',
            'services' => 'Sewa mobil lepas kunci',
            'address' => 'Jl. Penyelesaian No. 1',
            'contact' => '081234567890',
            'operating_hours' => '08.00-21.00 WIB',
            'terms' => 'Ketentuan uji penyelesaian transaksi.',
            'bank_name' => 'Bank Uji DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Uji',
        ], ['id']);
    }

    public function test_three_account_full_lifecycle_through_additional_charges_and_completion(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(500000);

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(3)->startOfHour();

        // 1. Akun Pelanggan memesan & mengirim bukti bayar utama.
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('main-pay.jpg'),
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $payment = $rental->payments()->firstOrFail();

        // 2. Akun Admin memverifikasi pelunasan -> status confirmed.
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.bookings.show', $rental));

        // 3. Akun Staf mengonfirmasi serah-terima (picked_up) lalu mencatat pengembalian telat 2 jam + kerusakan (returned).
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'odometer_km' => 12000,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('pickup.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental));

        $rental->refresh();
        $lateReturnAt = $rental->return_at->copy()->timezone('Asia/Jakarta')->addMinutes(75); // 2 jam telat -> 100.000

        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $lateReturnAt->format('Y-m-d\TH:i'),
            'odometer_km' => 12350,
            'fuel_level' => 'half',
            'has_damage' => 1,
            'damage_description' => 'Velg depan kiri tergores trotoar.',
            'condition_photo' => UploadedFile::fake()->image('return-damage.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::STATUS_RETURNED, $rental->rental_status);
        $this->assertEquals(100000.0, (float) $rental->late_fee_amount);

        // Tidak boleh menutup transaksi sebelum admin memeriksa & menetapkan biaya tambahan akhir (SYS-12).
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertSessionHasErrors('completion');

        // Staf dilarang menetapkan atau memverifikasi biaya tambahan (403) (A-09, SYS-01).
        $this->actingAs($staff)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 50000,
            'fuel_fee_reason' => 'Kurang BBM setengah tangki.',
            'damage_fee_amount' => 250000,
            'damage_fee_reason' => 'Poles velg.',
        ])->assertForbidden();

        // Admin menetapkan biaya BBM dan kerusakan -> total tambahan = 100.000 + 80.000 + 250.000 = 430.000 (SYS-11).
        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 80000,
            'fuel_fee_reason' => 'BBM kembali setengah tangki dari posisi penuh saat ambil.',
            'damage_fee_amount' => 250000,
            'damage_fee_reason' => 'Biaya perbaikan velg depan kiri tergores.',
            'damage_evidence' => UploadedFile::fake()->image('damage-evidence.jpg'),
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $this->assertEquals(430000.0, (float) $rental->additional_charges_total);
        $this->assertSame(Rental::ADDITIONAL_CHARGE_UNPAID, $rental->additional_charge_status);

        // Masih belum boleh menyelesaikan transaksi selama tagihan tambahan belum lunas (SYS-12).
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertSessionHasErrors('completion');

        // Pelanggan lain dilarang mengirim bukti ke pesanan ini (403).
        $this->actingAs($otherCustomer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('other.jpg'),
        ])->assertForbidden();

        // Pelanggan mengirim bukti pembayaran tagihan tambahan secara privat (C-10).
        $this->actingAs($customer)->post(route('customer.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('add-proof.jpg'),
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::ADDITIONAL_CHARGE_PENDING_APPROVAL, $rental->additional_charge_status);

        // Bukti pembayaran tagihan tambahan hanya dapat diakses pemilik & admin (NFR-01).
        $proofUrl = route('documents.additional-charge-proof.show', $rental);
        $ownerRes = $this->actingAs($customer)->get($proofUrl);
        $ownerRes->assertOk();
        $this->assertStringContainsString('no-store', (string) $ownerRes->headers->get('Cache-Control'));

        $this->actingAs($admin)->get($proofUrl)->assertOk();
        $this->actingAs($staff)->get($proofUrl)->assertForbidden();
        $this->actingAs($otherCustomer)->get($proofUrl)->assertForbidden();

        // Admin memverifikasi pelunasan dengan referensi pembayaran (A-09).
        $this->actingAs($admin)->patch(route('admin.bookings.additional-charges.approve', $rental), [
            'verified_amount' => 430000,
            'payment_reference' => 'TRF-ADD-430K',
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::ADDITIONAL_CHARGE_PAID, $rental->additional_charge_status);
        $this->assertSame('TRF-ADD-430K', $rental->additional_charge_payment_reference);

        // Admin menutup transaksi menjadi completed; status perawatan mobil tetap terjaga terpisah (SYS-12).
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental), [
            'completion_notes' => 'Tagihan tambahan lunas; unit tetap masuk bengkel untuk velg.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $car->refresh();
        $this->assertSame(Rental::STATUS_COMPLETED, $rental->rental_status);
        $this->assertNotNull($rental->completed_at);
        $this->assertSame($admin->id, $rental->completed_by);
        $this->assertSame('maintenance', $car->status);
        $this->assertSame(Car::READINESS_NEEDS_MAINTENANCE, $car->readiness_status);

        $this->actingAs($customer)->get(route('customer.bookings.show', $rental))
            ->assertOk()
            ->assertSee('TRF-ADD-430K')
            ->assertSee('Tagihan Tambahan Lunas');
    }

    public function test_completion_blocks_unresolved_extension_refund_and_cleans_up_failed_proof_uploads(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $car = $this->createBookableCar(400000);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addHours(4)->startOfHour();

        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);
        $pay = app(RentalPaymentService::class)->submitProof($rental, $customer, UploadedFile::fake()->image('m.jpg'));
        app(RentalPaymentService::class)->approvePayment($pay, $admin, (float) $rental->total_amount, now('Asia/Jakarta'));
        $rental->refresh();

        // Buat pengajuan perpanjangan yang batal diaktifkan setelah uang transfer perpanjangan masuk -> refund_pending (A2).
        $extService = app(RentalExtensionService::class);
        $extension = $extService->requestExtension($rental, $customer, 1);
        $extService->approveExtension($extension, $admin);
        $extService->submitExtensionPayment($extension, $customer, UploadedFile::fake()->image('ext.jpg'));
        $extService->rejectExtensionPayment($extension, $admin, 'Slot bentrok saat verifikasi tahap akhir.', 400000, true, now('Asia/Jakarta'));

        // Serah-terima & pengembalian bersih tepat waktu.
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickupAt->format('Y-m-d\TH:i'),
            'odometer_km' => 5000,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('p.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental));

        $rental->refresh();
        $this->actingAs($staff)->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => $rental->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 5150,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('r.jpg'),
        ])->assertRedirect(route('staff.schedules.show', $rental));

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 0,
            'damage_fee_amount' => 0,
        ])->assertRedirect(route('admin.bookings.show', $rental));

        // Penutupan transaksi wajib tertolak selama masih ada kewajiban refund perpanjangan yang belum ditransfer (A2, SYS-12).
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertSessionHasErrors('completion');

        // Setelah admin merealisasikan refund perpanjangan -> penutupan transaksi berhasil.
        $extService->realizeExtensionRefund($extension->fresh(), $admin, 400000, now('Asia/Jakarta'), 'REF-EXT-CLEAR');
        $this->actingAs($admin)->patch(route('admin.bookings.complete', $rental))
            ->assertRedirect(route('admin.bookings.show', $rental));

        $this->assertSame(Rental::STATUS_COMPLETED, $rental->fresh()->rental_status);

        // Uji pembersihan berkas saat DB gagal pada unggahan bukti tagihan tambahan (NFR-04).
        $rental2 = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt->addDays(4), 1, true);
        $pay2 = app(RentalPaymentService::class)->submitProof($rental2, $customer, UploadedFile::fake()->image('m2.jpg'));
        app(RentalPaymentService::class)->approvePayment($pay2, $admin, (float) $rental2->total_amount, now('Asia/Jakarta'));

        // Siapkan kendaraan sebelum serah-terima rental2.
        app(RentalOperationService::class)->markVehicleReady($car, $staff);
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental2), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickupAt->addDays(4)->format('Y-m-d\TH:i'),
            'odometer_km' => 6000,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('p2.jpg'),
        ])->assertRedirect();

        $rental2->refresh();
        $this->actingAs($staff)->post(route('staff.schedules.return', $rental2), [
            'actual_return_at' => $rental2->return_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 6100,
            'fuel_level' => 'half',
            'condition_photo' => UploadedFile::fake()->image('r2.jpg'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental2), [
            'fuel_fee_amount' => 60000,
            'fuel_fee_reason' => 'Kurang BBM.',
            'damage_fee_amount' => 0,
        ])->assertRedirect();

        $beforeCount = count(Storage::disk('local')->allFiles('payments/additional'));
        $shouldFail = true;
        DB::listen(static function ($query) use (&$shouldFail): void {
            if ($shouldFail && str_contains($query->sql, 'insert into "rental_histories"')) {
                throw new RuntimeException('Simulasi gagal simpan bukti tambahan.');
            }
        });

        try {
            app(RentalSettlementService::class)->submitAdditionalChargePayment(
                $rental2->fresh(),
                $customer,
                UploadedFile::fake()->image('orphan-add.jpg')
            );
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulasi gagal', $e->getMessage());
        } finally {
            $shouldFail = false;
        }

        $afterCount = count(Storage::disk('local')->allFiles('payments/additional'));
        $this->assertSame($beforeCount, $afterCount);
        $this->assertSame(Rental::ADDITIONAL_CHARGE_UNPAID, $rental2->fresh()->additional_charge_status);
        $this->assertInstanceOf(RentalExtension::class, $extension);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Settle '.uniqid(),
            'email' => $roleName.'_set_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Settle '.uniqid(),
            'email' => 'cust_set_'.uniqid().'@driveease.test',
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
        $carType = CarType::firstOrCreate(['name' => 'Sedan Settlement'], ['description' => 'Tipe uji penyelesaian']);

        return Car::create([
            'car_type_id' => $carType->id,
            'name' => 'Honda Civic Settle '.substr(uniqid(), -5),
            'plate_number' => 'B '.random_int(1000, 9999).' ST',
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
