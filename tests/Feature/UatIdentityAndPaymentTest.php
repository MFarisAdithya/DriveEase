<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\RentalOperationService;
use App\Services\RentalScheduleService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UatIdentityAndPaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_two_orders_for_one_customer_confirm_only_after_both_checks_in_either_order(): void
    {
        [$admin, $customer, $car] = $this->prepare();
        $first = $this->book($customer, $car, now('Asia/Jakarta')->addDay());
        $second = $this->book($customer, $car, now('Asia/Jakarta')->addDays(3));

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $first), [
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'claimed_amount' => 1,
        ])->assertSessionHasNoErrors();
        $proof = $first->payments()->sole();
        $this->assertEquals($first->total_amount, $proof->claimed_amount);
        $this->pay($admin, $first);
        $this->assertSame(Rental::PAYMENT_PAID, $first->fresh()->payment_status);
        $this->assertSame(Rental::STATUS_PENDING, $first->fresh()->rental_status);
        $this->assertFalse(app(RentalOperationService::class)->evaluateHandoverReadiness($first->fresh())['ready']);

        $this->patch(route('admin.verifications.approve', $customer))->assertSessionHas('success');
        $this->patch(route('admin.verifications.approve', $customer))->assertSessionHas('success');
        $this->assertSame(Rental::STATUS_CONFIRMED, $first->fresh()->rental_status);
        $this->assertSame(1, $first->histories()->where('action', 'identity_approved')->count());
        $this->assertSame(Rental::IDENTITY_VERIFIED, $second->fresh()->identity_status);
        $this->assertSame(Rental::STATUS_PENDING, $second->fresh()->rental_status);

        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $second), [
            'payment_proof' => UploadedFile::fake()->image('second.png'),
        ])->assertSessionHasNoErrors();
        $this->pay($admin, $second);
        $this->assertSame(Rental::STATUS_CONFIRMED, $second->fresh()->rental_status);
        $this->assertSame(1, $second->cashFlows()->count());
    }

    public function test_walk_in_correction_keeps_first_deadline_and_closed_order_reconciles_cash_once(): void
    {
        [$admin, $customer, $car] = $this->prepare(true);
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addHours(8), $admin);

        $reject = route('admin.verifications.reject', $customer);
        $this->actingAs($admin)->patch($reject, ['rejection_reason' => 'Foto KTP kurang jelas.'])->assertSessionHas('success');
        $deadline = $rental->fresh()->identity_deadline_at;
        $this->assertSame('15:00', $deadline->format('H:i'));

        $this->post(route('admin.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('walk-in.jpg'),
        ])->assertSessionHasNoErrors();

        $this->travel(30)->minutes();
        $this->patch(route('admin.verifications.correct-walk-in', $customer), [
            'ktp_image' => UploadedFile::fake()->image('ktp-corrected.jpg'),
        ])->assertSessionHas('success');
        $this->patch($reject, ['rejection_reason' => 'Foto pengganti belum bisa dibaca.'])->assertSessionHas('success');
        $this->patch($reject, ['rejection_reason' => 'Pengiriman keputusan yang sama.'])->assertSessionHas('success');
        $this->assertTrue($deadline->equalTo($rental->fresh()->identity_deadline_at));
        $this->assertSame(2, $rental->histories()->where('action', 'identity_rejected')->count());

        $this->travelTo($deadline);
        $this->patch(route('admin.verifications.correct-walk-in', $customer), [
            'ktp_image' => UploadedFile::fake()->image('too-late.jpg'),
        ])->assertSessionHasErrors('identity');

        $this->assertSame(1, app(RentalScheduleService::class)->releaseExpiredHolds($car->id));
        $this->assertSame(0, app(RentalScheduleService::class)->releaseExpiredHolds($car->id));
        $this->assertSame(Rental::CANCEL_IDENTITY, $rental->fresh()->cancellation_type);
        $this->assertFalse($rental->fresh()->isHoldingSchedule());

        $proof = $rental->payments()->sole();
        $route = route('admin.payments.approve', $proof);
        $receipt = ['verified_amount' => 100000, 'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i')];
        $this->patch($route, $receipt)->assertSessionHasNoErrors();
        $this->patch($route, $receipt)->assertSessionHasErrors('payment');
        $rental->refresh();
        $this->assertSame(Rental::STATUS_CANCELLED, $rental->rental_status);
        $this->assertEquals(100000, $rental->refund_amount);
        $this->assertSame(Rental::REFUND_PENDING, $rental->refund_status);
        $this->assertSame(1, $rental->cashFlows()->count());
    }

    public function test_scheduler_marks_late_review_once_releases_at_end_and_does_not_apply_no_show(): void
    {
        [$admin, $customer, $car] = $this->prepare();
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addHours(2));
        $this->actingAs($customer)->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('timely.jpg'),
        ])->assertSessionHasNoErrors();

        $this->travelTo($rental->pickup_at);
        $scheduler = app(RentalScheduleService::class);
        $this->assertSame(0, $scheduler->releaseExpiredHolds($car->id));
        $this->assertSame(0, $scheduler->releaseExpiredHolds($car->id));
        $this->assertSame(1, $rental->histories()->where('action', 'admin_review_followup')->count());
        $this->assertTrue($rental->fresh()->isHoldingSchedule());
        $this->actingAs($admin)->post(route('admin.bookings.review-contact', $rental), [
            'confirmation_method' => 'phone',
            'contacted_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'contact_notes' => 'Pelanggan menunggu hasil pemeriksaan dokumen.',
        ])->assertSessionHas('success');

        $this->travelTo($rental->return_at);
        $this->assertFalse($rental->fresh()->isHoldingSchedule());
        $this->assertFalse(Rental::query()->holdingSchedule()->whereKey($rental->id)->exists());
        $this->patch(route('admin.verifications.approve', $customer))->assertSessionHas('success');
        $this->pay($admin, $rental);
        $this->assertSame(Rental::STATUS_PENDING, $rental->fresh()->rental_status);
        $this->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_NO_SHOW,
            'cancellation_reason' => 'Permintaan pembatalan karena menunggu.',
            'contact_attempted' => 1,
        ])->assertSessionHasErrors('cancellation_type');
        $this->patch(route('admin.bookings.cancel-with-refund', $rental), [
            'cancellation_type' => Rental::CANCEL_RENTAL_FAULT,
            'cancellation_reason' => 'Pemeriksaan admin terlambat.',
        ])->assertSessionHasNoErrors();
        $this->assertEquals($rental->total_amount, $rental->fresh()->refund_amount);
    }

    public function test_repeated_payment_rejection_keeps_first_repair_deadline(): void
    {
        [$admin, $customer, $car] = $this->prepare();
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addDay());
        $upload = route('customer.bookings.payments.store', $rental);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->actingAs($customer)->post($upload, [
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])->assertSessionHasNoErrors();
            $this->actingAs($admin)->patch(route('admin.payments.reject', $rental->payments()->latest('id')->firstOrFail()), [
                'rejection_reason' => 'Bukti tidak dapat dicocokkan.',
                'received_amount' => 0,
            ])->assertSessionHasNoErrors();
            $this->assertSame('10:00', $rental->fresh()->payment_deadline_at->format('H:i'));
            $this->travel(15)->minutes();
        }

        $this->travelTo($rental->fresh()->payment_deadline_at);
        $this->actingAs($customer)->post($upload, [
            'payment_proof' => UploadedFile::fake()->image('late.jpg'),
        ])->assertSessionHasErrors('payment_proof');
        app(RentalScheduleService::class)->releaseExpiredHolds($car->id);
        $this->assertSame(Rental::STATUS_EXPIRED, $rental->fresh()->rental_status);
    }

    public function test_equal_expiry_uses_identity_reason_but_earlier_payment_expiry_keeps_payment_reason(): void
    {
        [$admin, $customer, $car] = $this->prepare();
        $same = $this->book($customer, $car, now('Asia/Jakarta')->addHours(2));
        $later = $this->book($customer, $car, now('Asia/Jakarta')->addDays(2));
        $this->actingAs($admin)->patch(route('admin.verifications.reject', $customer), [
            'rejection_reason' => 'Dokumen tidak terbaca.',
        ])->assertSessionHas('success');

        $this->travelTo($same->pickup_at);
        $this->patch(route('admin.bookings.cancel-with-refund', $same), [
            'cancellation_type' => Rental::CANCEL_RENTAL_FAULT,
            'cancellation_reason' => 'Percobaan mengganti alasan yang sudah jatuh tempo.',
        ])->assertSessionHasErrors('rental_status');
        app(RentalScheduleService::class)->releaseExpiredHolds($car->id);
        $this->assertSame(Rental::CANCEL_IDENTITY, $same->fresh()->cancellation_type);
        $this->assertSame(Rental::CANCEL_PAYMENT_EXPIRED, $later->fresh()->cancellation_type);
    }

    public function test_sla_only_counts_operating_hours_and_late_rejection_does_not_expire_booking(): void
    {
        [$admin, $customer, $car] = $this->prepare();
        $this->travelTo(now('Asia/Jakarta')->setTime(20, 45));
        $customer->forceFill(['identity_submitted_at' => now('Asia/Jakarta')])->saveOrFail();
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addDay()->setTime(9, 0));
        $this->actingAs($customer)->get(route('customer.bookings.show', $rental))
            ->assertOk()->assertSee($rental->payment_account_number)->assertSee('Batas unggah');
        $this->post(route('customer.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('timely.jpg'),
        ])->assertSessionHasNoErrors();

        $this->travelTo(now('Asia/Jakarta')->addDay()->setTime(8, 44));
        $this->assertFalse(RentalScheduleService::reviewOverdue($customer->identity_submitted_at, now('Asia/Jakarta')));
        $this->travel(1)->minute();
        $this->assertTrue(RentalScheduleService::reviewOverdue($customer->identity_submitted_at, now('Asia/Jakarta')));

        $this->actingAs($admin)->patch(route('admin.verifications.reject', $customer), [
            'rejection_reason' => 'Nomor dokumen belum bisa diperiksa.',
        ])->assertSessionHas('success');
        $this->assertSame(Rental::IDENTITY_FOLLOWUP, $rental->fresh()->identity_status);
        $this->travelTo($rental->pickup_at);
        $this->assertSame(0, app(RentalScheduleService::class)->releaseExpiredHolds($car->id));
        $this->assertSame(Rental::STATUS_PENDING, $rental->fresh()->rental_status);
    }

    /** @return array{User, User, Car} */
    private function prepare(bool $walkIn = false): array
    {
        $this->travelTo(now('Asia/Jakarta')->setTime(9, 0));
        RentalSetting::updateOrCreate(['id' => 1], [
            'operating_hours' => '08.00–21.00 WIB',
            'bank_name' => 'Bank Uji',
            'account_number' => '123456789',
            'account_holder' => 'DriveEase Uji',
        ] + (array) RentalSetting::defaultPublicInfo());
        $admin = User::factory()->for(Role::where('name', 'admin')->firstOrFail())->create();
        $fields = ['ktp_number' => '3578012304950001', 'sim_number' => '12345678901234',
            'ktp_image' => 'identities/ktp.jpg', 'sim_image' => 'identities/sim.jpg',
            'identity_submitted_at' => now('Asia/Jakarta')];
        if ($walkIn) {
            $fields += ['email' => null, 'password' => null, 'remember_token' => null, 'email_verified_at' => null];
        }
        $customer = User::factory()->for(Role::where('name', 'customer')->firstOrFail())->create($fields)->refresh();
        Storage::disk('local')->put($customer->ktp_image, 'fixture');
        Storage::disk('local')->put($customer->sim_image, 'fixture');

        return [$admin, $customer, Car::bookableForSchedule()->firstOrFail()];
    }

    private function book(User $customer, Car $car, CarbonInterface $pickup, ?User $admin = null): Rental
    {
        $this->actingAs($admin ?? $customer)->post(route($admin ? 'admin.bookings.store' : 'customer.bookings.store'), [
            'customer_id' => $customer->id,
            'confirmation_method' => 'in_person',
            'car_id' => $car->id,
            'pickup_at' => $pickup->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasNoErrors();

        return $customer->rentals()->latest('id')->firstOrFail();
    }

    private function pay(User $admin, Rental $rental): void
    {
        $this->actingAs($admin)->patch(route('admin.payments.approve', $rental->payments()->sole()), [
            'verified_amount' => $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
    }
}
