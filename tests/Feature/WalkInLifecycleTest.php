<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Rental;
use App\Models\RentalChangeRequest;
use App\Models\RentalExtension;
use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WalkInLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_late_identity_rejection_gets_one_new_window_after_manual_reschedule(): void
    {
        [$admin, $staff, $customer, $car] = $this->prepare();
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addHours(2));
        $this->uploadMainProof($rental);

        $this->travelTo($rental->pickup_at);
        $this->patch(route('admin.verifications.reject', $customer), [
            'rejection_reason' => 'Dokumen belum bisa dibaca.',
        ])->assertSessionHas('success');

        // Saat penahanan berakhir, pesanan harus tetap ada pada daftar tindak lanjut.
        $this->travelTo($rental->return_at);
        $this->assertFalse($rental->fresh()->isHoldingSchedule());
        $this->get(route('admin.bookings.index', ['filter' => 'review_followup']))
            ->assertOk()->assertSee($rental->rental_code);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('tindak lanjut pemeriksaan');

        $pickup = now('Asia/Jakarta')->addHours(4);
        $change = $this->propose($rental, $pickup);
        $response = route('admin.change-requests.walk-in-response', $change);
        $this->actingAs($staff)->patch($response, $this->confirmation())->assertForbidden();
        $this->actingAs($admin)->patch($response, ['decision' => 'approve'])->assertSessionHasErrors('confirmation_method');
        $this->patch($response, $this->confirmation() + ['decision' => 'approve'])->assertSessionHas('success');
        $this->patch($response, $this->confirmation() + ['decision' => 'approve'])->assertSessionHasErrors('decision');

        $rental->refresh();
        $this->assertSame(Rental::IDENTITY_REJECTED, $rental->identity_status);
        $this->assertTrue($pickup->equalTo($rental->identity_deadline_at));
        $this->assertTrue($rental->isHoldingSchedule());
        $this->assertNull($rental->review_followup_at);
        $history = $rental->histories()->where('action', 'change_approved')->sole();
        $this->assertSame($admin->id, $history->actor_id);
        $this->assertSame('phone', $history->metadata['confirmation_method']);

        $this->patch(route('admin.verifications.correct-walk-in', $customer), [
            'ktp_image' => UploadedFile::fake()->image('corrected.jpg'),
        ])->assertSessionHas('success');
        $this->patch(route('admin.verifications.reject', $customer), [
            'rejection_reason' => 'Foto perbaikan belum jelas.',
        ])->assertSessionHas('success');
        $this->assertTrue($pickup->equalTo($rental->fresh()->identity_deadline_at));
        $this->assertSame(Rental::IDENTITY_REJECTED, $rental->fresh()->identity_status);
    }

    public function test_manual_response_cannot_override_online_customer_and_checks_conflicts_again(): void
    {
        [$admin, , $customer, $car] = $this->prepare();
        $rental = $this->book($customer, $car, now('Asia/Jakarta')->addDays(2));
        $pickup = now('Asia/Jakarta')->addDays(5);
        $change = $this->propose($rental, $pickup);

        // Unit belum diblokir oleh usulan. Pesanan lain yang sah bisa masuk
        // sebelum persetujuan; server wajib mengecek ulang dan tidak mengubah jadwal.
        $other = $this->book($customer, $car, $pickup);
        $this->patch(route('admin.change-requests.walk-in-response', $change), $this->confirmation() + [
            'decision' => 'approve',
        ])->assertSessionHasErrors('decision');
        $this->assertSame(RentalChangeRequest::STATUS_PENDING_CUSTOMER, $change->fresh()->status);
        $this->assertFalse($pickup->equalTo($rental->fresh()->pickup_at));
        $this->assertTrue($other->fresh()->isHoldingSchedule());

        $online = User::factory()->for(Role::where('name', 'customer')->sole())->create(['verification_status' => 'verified']);
        $this->actingAs($online)->post(route('customer.bookings.store'), [
            'car_id' => $car->id, 'pickup_at' => $pickup->copy()->addDays(3)->format('Y-m-d\TH:i'),
            'total_days' => 1, 'accept_terms' => '1',
        ])->assertSessionHasNoErrors();
        $onlineRental = $online->rentals()->sole();
        $this->actingAs($admin);
        $onlineChange = $this->propose($onlineRental, $pickup->copy()->addDays(6));
        $this->patch(route('admin.change-requests.walk-in-response', $onlineChange), $this->confirmation() + [
            'decision' => 'approve',
        ])->assertForbidden();
        $this->actingAs($online)->patch(route('customer.change-requests.respond', $onlineChange), [
            'decision' => 'approve',
        ])->assertSessionHasNoErrors();
    }

    public function test_walk_in_replacement_extension_and_additional_payment_complete_without_customer_login(): void
    {
        [$admin, $staff, $customer, $car] = $this->prepare();
        $pickup = now('Asia/Jakarta')->addDays(3);
        $rental = $this->book($customer, $car, $pickup);
        $originalTotal = $rental->total_amount;
        $replacement = Car::readyForRental()->where('id', '!=', $car->id)->firstOrFail();

        $change = $this->propose($rental, $pickup, $replacement);
        $this->patch(route('admin.change-requests.walk-in-response', $change), $this->confirmation() + [
            'decision' => 'approve',
        ])->assertSessionHas('success');
        $rental->refresh();
        $this->assertSame($replacement->id, $rental->car_id);
        $this->assertEquals($originalTotal, $rental->total_amount);

        $this->uploadMainProof($rental);
        $this->patch(route('admin.payments.approve', $rental->payments()->sole()), [
            'verified_amount' => $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(Rental::STATUS_PENDING, $rental->fresh()->rental_status);
        $this->patch(route('admin.verifications.approve', $customer))->assertSessionHas('success');

        $requestExtension = route('admin.bookings.walk-in-extensions.store', $rental);
        $this->post($requestExtension, ['additional_days' => 1])->assertSessionHasErrors('confirmation_method');
        $this->post($requestExtension, $this->confirmation() + ['additional_days' => 1])->assertSessionHas('success');
        $this->post($requestExtension, $this->confirmation() + ['additional_days' => 1])->assertSessionHasErrors('additional_days');
        $extension = $rental->extensions()->sole();
        $history = $rental->histories()->where('action', 'extension_requested')->sole();
        $this->assertSame($admin->id, $extension->requested_by);
        $this->assertSame($admin->id, $history->actor_id);
        $this->assertSame('phone', $history->metadata['confirmation_method']);

        $this->patch(route('admin.extensions.approve', $extension))->assertSessionHas('success');
        $uploadExtension = route('admin.extensions.walk-in-payment.store', $extension);
        $this->actingAs($staff)->post($uploadExtension)->assertForbidden();
        $this->actingAs($admin)->post($uploadExtension, [
            'payment_proof' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4
%%EOF
'),
        ])->assertSessionHas('success');
        Storage::disk('local')->assertExists($extension->fresh()->payment_proof);
        $this->get(route('documents.extension-payment.show', $extension))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($staff)->get(route('documents.extension-payment.show', $extension))->assertForbidden();

        $originalReturn = $rental->return_at;
        $this->assertTrue($originalReturn->equalTo($rental->fresh()->return_at));
        $this->actingAs($admin)->patch(route('admin.extensions.payment.approve', $extension), [
            'verified_amount' => $extension->additional_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');
        $this->assertSame(RentalExtension::STATUS_COMPLETED, $extension->fresh()->status);
        $rental->refresh();
        $this->assertTrue($originalReturn->copy()->addDay()->equalTo($rental->return_at));

        $this->travelTo($pickup);
        $this->actingAs($staff)->post(route('staff.schedules.pickup', $rental), [
            'identity_checked' => 1,
            'actual_pickup_at' => $pickup->format('Y-m-d\TH:i'),
            'odometer_km' => 5000,
            'fuel_level' => 'full',
            'condition_photo' => UploadedFile::fake()->image('pickup.jpg'),
        ])->assertSessionHasNoErrors();

        $this->travelTo($rental->return_at);
        $this->post(route('staff.schedules.return', $rental), [
            'actual_return_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'odometer_km' => 5150,
            'fuel_level' => 'half',
            'condition_photo' => UploadedFile::fake()->image('return.jpg'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.bookings.additional-charges.assess', $rental), [
            'fuel_fee_amount' => 60000,
            'fuel_fee_reason' => 'BBM kurang saat dikembalikan.',
            'damage_fee_amount' => 0,
        ])->assertSessionHas('success');
        $this->patch(route('admin.bookings.complete', $rental))->assertSessionHasErrors('completion');
        $this->post(route('admin.bookings.additional-charges.proof', $rental), [
            'additional_charge_proof' => UploadedFile::fake()->image('fuel.png'),
        ])->assertSessionHas('success');
        $this->patch(route('admin.bookings.additional-charges.approve', $rental), [
            'verified_amount' => 60000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'payment_reference' => 'WALK-FUEL-01',
        ])->assertSessionHas('success');
        $this->patch(route('admin.bookings.complete', $rental))->assertSessionHas('success');
        $this->assertSame(Rental::STATUS_COMPLETED, $rental->fresh()->rental_status);
        $this->assertSame(3, $rental->cashFlows()->count());
        $this->assertNull($customer->fresh()->password);
    }

    public function test_walk_in_cancellation_and_extension_refund_keep_actual_customer_time_and_cash_history(): void
    {
        [$admin, $staff, $customer, $car] = $this->prepare();
        $pickup = now('Asia/Jakarta')->addHours(36);
        $rental = $this->book($customer, $car, $pickup);
        $this->patch(route('admin.bookings.cancel', $rental))->assertSessionHasErrors('cancellation_type');
        $this->patch(route('admin.verifications.approve', $customer))->assertSessionHas('success');
        $this->uploadMainProof($rental);
        $this->patch(route('admin.payments.approve', $rental->payments()->sole()), [
            'verified_amount' => $rental->total_amount,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        // Jika dana perpanjangan masuk sebelum bukti sempat disimpan, menu
        // pembatalan perpanjangan tetap dapat merekonsiliasi dan mencatat refund.
        $this->post(route('admin.bookings.walk-in-extensions.store', $rental), $this->confirmation() + [
            'additional_days' => 1,
        ])->assertSessionHas('success');
        $extension = $rental->extensions()->sole();
        $this->patch(route('admin.extensions.approve', $extension))->assertSessionHas('success');
        $this->get(route('admin.bookings.show', $rental))->assertOk()->assertSee('Batalkan Perpanjangan');
        $this->patch(route('admin.extensions.payment.reject', $extension), [
            'rejection_reason' => 'Perpanjangan dibatalkan atas permintaan penyewa.',
            'received_amount' => 25000,
            'received_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'cancel_extension' => 1,
        ])->assertSessionHas('success');
        $this->assertEquals(25000, $extension->fresh()->refund_amount);
        $this->patch(route('admin.extensions.refund', $extension), [
            'transfer_amount' => 25000,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'WALK-EXT-REFUND',
        ])->assertSessionHas('success');
        $this->assertSame(RentalExtension::REFUND_PROCESSED, $extension->fresh()->refund_status);

        $this->travel(1)->hour();
        $consent = $this->confirmation();
        $this->travel(13)->hours();
        $cancel = route('admin.bookings.cancel-with-refund', $rental);
        $cancellation = [
            'cancellation_type' => Rental::CANCEL_CUSTOMER,
            'cancellation_reason' => 'Penyewa meminta pembatalan melalui telepon.',
        ];
        $this->actingAs($staff)->patch($cancel, $cancellation + $consent)->assertForbidden();
        $this->actingAs($admin)->patch($cancel, $cancellation)->assertSessionHasErrors('confirmation_method');
        $this->patch($cancel, $cancellation + $consent)->assertSessionHas('success');
        $rental->refresh();
        $this->assertSame(Rental::STATUS_CANCELLED, $rental->rental_status);
        $this->assertSame(75, (int) $rental->refund_percentage);
        $this->assertSame($consent['confirmed_at'], $rental->cancellation_requested_at->format('Y-m-d\TH:i'));
        $history = $rental->histories()->where('action', 'booking_cancelled_with_refund')->sole();
        $this->assertSame($admin->id, $history->actor_id);
        $this->assertSame('phone', $history->metadata['confirmation_method']);

        $refund = route('admin.bookings.refund', $rental);
        $transfer = [
            'transfer_amount' => $rental->refund_amount,
            'transferred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'refund_reference' => 'WALK-MAIN-REFUND',
        ];
        $this->patch($refund, $transfer)->assertSessionHas('success');
        $this->patch($refund, $transfer)->assertSessionHasErrors('refund');
        $this->assertSame(Rental::REFUND_PROCESSED, $rental->fresh()->refund_status);
        $this->assertSame(4, $rental->cashFlows()->count());
        $this->assertNull($customer->fresh()->email);
    }

    /** @return array{User, User, User, Car} */
    private function prepare(): array
    {
        $this->travelTo(now('Asia/Jakarta')->setTime(9, 0));
        RentalSetting::updateOrCreate(['id' => 1], [
            'bank_name' => 'Bank Uji',
            'account_number' => '123456789',
            'account_holder' => 'DriveEase Uji',
        ] + (array) RentalSetting::defaultPublicInfo());
        $admin = User::factory()->for(Role::where('name', 'admin')->sole())->create();
        $staff = User::factory()->for(Role::where('name', 'staff')->sole())->create();
        $response = $this->actingAs($admin)->post(route('admin.bookings.register-customer'), [
            'name' => 'Penyewa Walk-in Uji',
            'phone' => '081234567899',
            'ktp_number' => '3578012304950001',
            'sim_number' => '12345678901234',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.png'),
        ]);
        $customer = User::where('name', 'Penyewa Walk-in Uji')->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.verifications.show', $customer));
        $this->assertNull($customer->email);
        $this->assertNull($customer->password);

        return [$admin, $staff, $customer, Car::bookableForSchedule()->firstOrFail()];
    }

    private function book(User $customer, Car $car, CarbonInterface $pickup): Rental
    {
        $this->post(route('admin.bookings.store'), [
            'customer_id' => $customer->id,
            'confirmation_method' => 'in_person',
            'car_id' => $car->id,
            'pickup_at' => $pickup->format('Y-m-d\TH:i'),
            'total_days' => 1,
            'accept_terms' => '1',
        ])->assertSessionHasNoErrors();

        return $customer->rentals()->latest('id')->firstOrFail();
    }

    private function uploadMainProof(Rental $rental): void
    {
        $this->post(route('admin.bookings.payments.store', $rental), [
            'payment_proof' => UploadedFile::fake()->image('main.jpg'),
        ])->assertSessionHasNoErrors();
    }

    private function propose(Rental $rental, CarbonInterface $pickup, ?Car $replacement = null): RentalChangeRequest
    {
        $this->post(route('admin.bookings.change-requests.propose', $rental), [
            'request_type' => $replacement ? 'car_replacement' : 'reschedule',
            'proposed_car_id' => $replacement?->id,
            'proposed_pickup_at' => $pickup->format('Y-m-d\TH:i'),
            'waive_price_difference' => '1',
            'reason' => 'Perubahan jadwal atau unit disampaikan kepada penyewa.',
        ])->assertSessionHasNoErrors();

        return $rental->changeRequests()->latest('id')->firstOrFail();
    }

    /** @return array{confirmation_method: string, confirmed_at: string} */
    private function confirmation(): array
    {
        return ['confirmation_method' => 'phone', 'confirmed_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i')];
    }
}
