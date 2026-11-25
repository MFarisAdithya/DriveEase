<?php

namespace Tests\Feature;

use App\Models\Car;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancellationAndRefundTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Refund',
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

    public function test_refund_policy_calculates_exact_tiers_and_additional_charge_totals(): void
    {
        $financialService = app(RentalFinancialService::class);
        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(5)->setTime(10, 0);

        $rental = new Rental([
            'pickup_at' => $pickupAt,
            'paid_amount' => 1000000,
        ]);

        // >= 48 jam -> 100%
        $tier100 = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(48));
        $this->assertSame(100, $tier100['percentage']);
        $this->assertEquals(1000000.0, $tier100['amount']);

        // 24 sampai < 48 jam -> 75%
        $tier75 = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(47));
        $this->assertSame(75, $tier75['percentage']);
        $this->assertEquals(750000.0, $tier75['amount']);

        $tier75Boundary = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(24));
        $this->assertSame(75, $tier75Boundary['percentage']);

        // 6 sampai < 24 jam -> 50%
        $tier50 = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(23));
        $this->assertSame(50, $tier50['percentage']);
        $this->assertEquals(500000.0, $tier50['amount']);

        $tier50Boundary = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(6));
        $this->assertSame(50, $tier50Boundary['percentage']);

        // < 6 jam -> 0%
        $tier0 = $financialService->calculateRefundPolicy($rental, Rental::CANCEL_CUSTOMER, $pickupAt->subHours(5));
        $this->assertSame(0, $tier0['percentage']);
        $this->assertEquals(0.0, $tier0['amount']);

        // Kesalahan rental -> 100%, No show -> 0%.
        $this->assertSame(100, $financialService->calculateRefundPolicy($rental, Rental::CANCEL_RENTAL_FAULT)['percentage']);
        $this->assertSame(0, $financialService->calculateRefundPolicy($rental, Rental::CANCEL_NO_SHOW)['percentage']);

        // Total tagihan tambahan (SYS-11).
        $this->assertEquals(425000.0, $financialService->calculateAdditionalChargesTotal(150000, 75000, 200000));
        try {
            $financialService->calculateAdditionalChargesTotal(-1000, 0, 0);
            $this->fail('Nominal negatif harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('additional_charges', $exception->errors());
        }
    }

    public function test_customer_requests_cancellation_admin_processes_refund_and_cannot_exceed_paid_amount(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();

        $pickupAt = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(10, 0);
        $rental = app(RentalBookingService::class)->createBooking($customer, $car->id, $pickupAt, 1, true);
        $payment = app(RentalPaymentService::class)->submitProof($rental, $customer, UploadedFile::fake()->image('bayar.jpg'));
        app(RentalPaymentService::class)->approvePayment($payment, $admin, (float) $rental->total_amount);
        $rental->refresh();

        // Pelanggan mengajukan pembatalan (4 hari sebelum ambil -> estimasi 100%).
        $this->actingAs($customer)->post(route('customer.bookings.request-cancellation', $rental), [
            'cancellation_request_reason' => 'Perjalanan dinas ditunda oleh kantor.',
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $this->assertNotNull($rental->cancellation_requested_at);
        $this->assertSame(100, $rental->refund_percentage);

        // Staf dilarang memproses pembatalan atau refund (403).
        $this->actingAs($staff)->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_CUSTOMER,
            'cancellation_reason' => 'Coba staf',
        ])->assertForbidden();

        // Admin tidak boleh menetapkan refund melebihi dana diterima.
        $this->actingAs($admin)->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_CUSTOMER,
            'cancellation_reason' => 'Pelanggan membatalkan lebih dari 48 jam sebelumnya.',
            'refund_amount' => ((float) $rental->paid_amount) + 100000,
        ])->assertSessionHasErrors('refund_amount');

        // Admin memproses pembatalan sesuai kebijakan -> status cancelled, refund pending.
        $this->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_CUSTOMER,
            'cancellation_reason' => 'Disetujui sesuai pengajuan pelanggan >= 48 jam.',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::STATUS_CANCELLED, $rental->rental_status);
        $this->assertSame(Rental::REFUND_PENDING, $rental->refund_status);
        $this->assertEquals((float) $rental->paid_amount, (float) $rental->refund_amount);

        // Admin tidak boleh merealisasikan transfer refund melebihi dana diterima.
        $this->patch(route('admin.bookings.refund', $rental), [
            'transfer_amount' => ((float) $rental->paid_amount) + 50000,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'REF-OVER',
        ])->assertSessionHasErrors('transfer_amount');

        // Realisasi transfer sah.
        $this->patch(route('admin.bookings.refund', $rental), [
            'transfer_amount' => (float) $rental->paid_amount,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'TRF-REF-2026-01',
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $this->assertSame(Rental::REFUND_PROCESSED, $rental->refund_status);
        $this->assertSame('TRF-REF-2026-01', $rental->refund_reference);
        $this->assertSame($admin->id, $rental->refund_processed_by);

        // Pengiriman ulang realisasi refund ditolak (SYS-13).
        $this->patch(route('admin.bookings.refund', $rental), [
            'transfer_amount' => (float) $rental->paid_amount,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'TRF-REF-DUP',
        ])->assertSessionHasErrors('refund');

        // Pelanggan melihat nominal, status, dan referensi refund pada detail pesanannya (C-08).
        $this->actingAs($customer)->get(route('customer.bookings.show', $rental))
            ->assertOk()
            ->assertSee('Refund Sudah Ditransfer')
            ->assertSee('TRF-REF-2026-01');
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Refund '.uniqid(),
            'email' => $roleName.'_ref_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Refund '.uniqid(),
            'email' => 'cust_ref_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
