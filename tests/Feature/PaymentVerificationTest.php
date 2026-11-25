<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('rental_settings')->upsert([
            'id' => 1,
            'business_name' => 'DriveEase Uji Pembayaran',
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

    public function test_customer_uploads_private_payment_proof_and_admin_approves_booking(): void
    {
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('staff');
        $owner = $this->createVerifiedCustomer();
        $otherCustomer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();

        $rental = app(RentalBookingService::class)->createBooking(
            customer: $owner,
            carId: $car->id,
            pickupAt: CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(10, 0),
            totalDays: 2,
            termsAccepted: true,
        );

        $proofFile = UploadedFile::fake()->image('transfer.jpg', 600, 400);
        $this->actingAs($otherCustomer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => $proofFile,
            'claimed_amount' => (int) $rental->total_amount,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => $proofFile,
            'claimed_amount' => (int) $rental->total_amount,
        ])->assertRedirect(route('customer.bookings.show', $rental));

        $rental->refresh();
        $payment = $rental->payments()->firstOrFail();

        $this->assertSame(Rental::PAYMENT_PENDING_APPROVAL, $rental->payment_status);
        $this->assertSame(Rental::STATUS_PENDING, $rental->rental_status);
        Storage::disk('local')->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);

        // Privasi bukti transfer: tamu redirect, staf & pelanggan lain 403, pemilik & admin 200 no-store.
        auth()->logout();
        $this->get(route('documents.payment.show', $payment))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('documents.payment.show', $payment))->assertForbidden();
        $this->actingAs($otherCustomer)->get(route('documents.payment.show', $payment))->assertForbidden();

        $ownerView = $this->actingAs($owner)->get(route('documents.payment.show', $payment))->assertOk();
        $this->assertStringContainsString('no-store', (string) $ownerView->headers->get('Cache-Control'));

        $adminView = $this->actingAs($admin)->get(route('documents.payment.show', $payment))->assertOk();
        $this->assertStringContainsString('no-store', (string) $adminView->headers->get('Cache-Control'));

        // Approval kurang dari nominal tagihan harus ditolak.
        $this->actingAs($admin)->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => ((float) $rental->total_amount) - 50000,
        ])->assertSessionHasErrors('verified_amount');

        // Approval penuh melunasi dan mengonfirmasi pesanan.
        $this->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
        ])->assertRedirect(route('admin.bookings.show', $rental));

        $rental->refresh();
        $payment->refresh();
        $this->assertSame(Rental::PAYMENT_PAID, $rental->payment_status);
        $this->assertSame(Rental::STATUS_CONFIRMED, $rental->rental_status);
        $this->assertEquals((float) $rental->total_amount, (float) $rental->paid_amount);
        $this->assertSame($admin->id, $rental->payment_verified_by);
        $this->assertNotNull($rental->payment_verified_at);
        $this->assertSame(RentalPayment::STATUS_APPROVED, $payment->status);

        // Approval ulang tidak boleh menggandakan pembayaran.
        $this->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $rental->total_amount,
        ])->assertSessionHasErrors('payment');
    }

    public function test_rejection_with_partial_amount_sets_one_hour_fix_deadline_and_reupload_does_not_extend_it(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();

        $rental = app(RentalBookingService::class)->createBooking(
            customer: $customer,
            carId: $car->id,
            pickupAt: CarbonImmutable::now('Asia/Jakarta')->addDays(2)->setTime(14, 0),
            totalDays: 2,
            termsAccepted: true,
        );

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('partial.jpg'),
            'claimed_amount' => (int) $rental->total_amount,
        ])->assertRedirect();

        $firstPayment = $rental->payments()->firstOrFail();

        // Penolakan tanpa alasan wajib gagal validasi.
        $this->actingAs($admin)->patch(route('admin.payments.reject', $firstPayment), [
            'rejection_reason' => '',
        ])->assertSessionHasErrors('rejection_reason');

        // Admin menolak dengan alasan dan mencatat dana masuk sebagian sebesar 300.000.
        $partialReceived = 300000;
        $this->patch(route('admin.payments.reject', $firstPayment), [
            'rejection_reason' => 'Transfer kurang dari total tagihan, silakan lunasi kekurangannya.',
            'received_amount' => $partialReceived,
        ])->assertRedirect();

        $rental->refresh();
        $this->assertSame(Rental::PAYMENT_REJECTED, $rental->payment_status);
        $this->assertSame(Rental::STATUS_PENDING, $rental->rental_status);
        $this->assertEquals($partialReceived, (float) $rental->paid_amount);
        $fixDeadline = $rental->payment_deadline_at->copy();
        $this->assertNotNull($fixDeadline);

        // Pelanggan mengunggah ulang sisa pembayaran 5 menit kemudian: tenggat TIDAK diperpanjang otomatis (SYS-06).
        $this->travel(5)->minutes();
        $remaining = $rental->remainingBalance();
        $this->assertEquals(((float) $rental->total_amount) - $partialReceived, $remaining);

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('shortfall.jpg'),
            'claimed_amount' => $remaining,
        ])->assertRedirect();

        $rental->refresh();
        $this->assertTrue($fixDeadline->equalTo($rental->payment_deadline_at));

        // Admin memverifikasi pelunasan sisa tagihan -> lunas & terkonfirmasi.
        $secondPayment = $rental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.approve', $secondPayment), [
            'verified_amount' => $remaining,
        ])->assertRedirect();

        $rental->refresh();
        $this->assertSame(Rental::PAYMENT_PAID, $rental->payment_status);
        $this->assertSame(Rental::STATUS_CONFIRMED, $rental->rental_status);
        $this->assertEquals((float) $rental->total_amount, (float) $rental->paid_amount);
    }

    public function test_overdue_verification_warns_admin_without_auto_cancel_and_expired_rejected_releases_hold(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $cars = Car::readyForRental()->take(2)->get();

        // Pesanan 1: bukti sudah dikirim > 1 jam -> tampil peringatan SLA, tidak dibatalkan otomatis.
        $overdueVerifyRental = app(RentalBookingService::class)->createBooking(
            customer: $customer,
            carId: $cars[0]->id,
            pickupAt: CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0),
            totalDays: 1,
            termsAccepted: true,
        );
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $overdueVerifyRental), [
            'payment_proof' => UploadedFile::fake()->image('proof1.jpg'),
            'claimed_amount' => (int) $overdueVerifyRental->total_amount,
        ])->assertRedirect();
        $overdueVerifyRental->forceFill([
            'payment_submitted_at' => CarbonImmutable::now('Asia/Jakarta')->subMinutes(65),
        ])->save();

        $this->actingAs($admin)->get(route('admin.bookings.show', $overdueVerifyRental))
            ->assertOk()
            ->assertSee('Peringatan SLA Verifikasi');

        $this->artisan('rentals:expire-unpaid')->assertSuccessful();
        $this->assertSame(Rental::STATUS_PENDING, $overdueVerifyRental->fresh()->rental_status);

        // Pesanan 2: bukti ditolak dan melewati tenggat 1 jam -> dilepas otomatis oleh scheduler command.
        $expiredRejectedRental = app(RentalBookingService::class)->createBooking(
            customer: $customer,
            carId: $cars[1]->id,
            pickupAt: CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0),
            totalDays: 1,
            termsAccepted: true,
        );
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $expiredRejectedRental), [
            'payment_proof' => UploadedFile::fake()->image('proof2.jpg'),
            'claimed_amount' => (int) $expiredRejectedRental->total_amount,
        ])->assertRedirect();
        $payment2 = $expiredRejectedRental->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.reject', $payment2), [
            'rejection_reason' => 'Foto tidak terbaca.',
        ])->assertRedirect();
        $expiredRejectedRental->forceFill([
            'payment_deadline_at' => CarbonImmutable::now('Asia/Jakarta')->subMinute(),
        ])->save();

        $this->artisan('rentals:expire-unpaid')->assertSuccessful();
        $this->assertSame(Rental::STATUS_EXPIRED, $expiredRejectedRental->fresh()->rental_status);
    }

    public function test_walk_in_payment_proof_upload_and_db_failure_rollback_cleans_orphan_file(): void
    {
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createVerifiedCustomer();
        $car = Car::readyForRental()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'pickup_at' => CarbonImmutable::now('Asia/Jakarta')->addDays(4)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertRedirect();

        $walkInRental = Rental::where('user_id', $customer->id)->latest('id')->firstOrFail();

        // Simulasikan kegagalan simpan DB saat unggah bukti -> file baru harus dibersihkan.
        $dispatcher = RentalPayment::getEventDispatcher();
        $isolated = clone $dispatcher;
        $isolated->listen('eloquent.creating: '.RentalPayment::class, function (): void {
            throw new \RuntimeException('Simulated DB error');
        });
        RentalPayment::setEventDispatcher($isolated);
        try {
            $this->actingAs($admin)->post(route('admin.bookings.payments.store', $walkInRental), [
                'payment_proof' => UploadedFile::fake()->image('fail.jpg'),
                'claimed_amount' => (int) $walkInRental->total_amount,
            ])->assertSessionHas('error');
        } finally {
            RentalPayment::setEventDispatcher($dispatcher);
        }

        $this->assertSame([], Storage::disk('local')->allFiles('payments/booking'));
        $this->assertSame(Rental::PAYMENT_UNPAID, $walkInRental->fresh()->payment_status);

        // Unggah normal berhasil lalu diverifikasi admin.
        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $walkInRental), [
            'payment_proof' => UploadedFile::fake()->image('walkin.jpg'),
            'claimed_amount' => (int) $walkInRental->total_amount,
        ])->assertRedirect();

        $payment = $walkInRental->payments()->firstOrFail();
        $this->patch(route('admin.payments.approve', $payment), [
            'verified_amount' => (float) $walkInRental->total_amount,
        ])->assertRedirect();

        $this->assertSame(Rental::STATUS_CONFIRMED, $walkInRental->fresh()->rental_status);
        $this->assertSame(Rental::PAYMENT_PAID, $walkInRental->fresh()->payment_status);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::create([
            'role_id' => $role->id,
            'name' => ucfirst($roleName).' Payment '.uniqid(),
            'email' => $roleName.'_pay_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
        ]);
    }

    private function createVerifiedCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        return User::create([
            'role_id' => $role->id,
            'name' => 'Customer Pay '.uniqid(),
            'email' => 'cust_pay_'.uniqid().'@driveease.test',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'ktp_number' => '3174012345678901',
            'sim_number' => '12345678901234',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
