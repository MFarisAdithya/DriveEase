<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $staff;
    protected User $customer;
    protected User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $staffRole = Role::firstOrCreate(['name' => 'staff'], ['display_name' => 'Staf Operasional']);
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Pelanggan']);

        $unique = uniqid();

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Verification Tester',
            'email' => "admin_verif_{$unique}@driveease.test",
            'password' => bcrypt('password123'),
        ]);

        $this->staff = User::create([
            'role_id' => $staffRole->id,
            'name' => 'Staff Verification Tester',
            'email' => "staff_verif_{$unique}@driveease.test",
            'password' => bcrypt('password123'),
        ]);

        $this->customer = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Budi Customer',
            'email' => "budi_verif_{$unique}@driveease.test",
            'password' => bcrypt('password123'),
            'verification_status' => 'pending',
        ]);

        $this->otherCustomer = User::create([
            'role_id' => $customerRole->id,
            'name' => 'Siti Customer',
            'email' => "siti_verif_{$unique}@driveease.test",
            'password' => bcrypt('password123'),
            'verification_status' => 'pending',
        ]);
    }

    public function test_guest_cannot_access_verification_page(): void
    {
        $response = $this->get(route('customer.verification.create'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_access_verification_page(): void
    {
        $response = $this->actingAs($this->customer)->get(route('customer.verification.create'));
        $response->assertOk();
        $response->assertSee('Verifikasi Identitas Pelanggan');
        $response->assertSee('Budi Customer');
    }

    public function test_customer_can_submit_valid_identity_documents(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp.jpg', 600, 400)->size(500);
        $simFile = UploadedFile::fake()->image('sim.png', 600, 400)->size(600);

        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => '1234-5678-901234',
            'ktp_image' => $ktpFile,
            'sim_image' => $simFile,
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHas('success');

        $this->customer->refresh();

        $this->assertEquals('3578012304950001', $this->customer->ktp_number);
        $this->assertEquals('3578012304950001', $this->customer->nik_ktp);
        $this->assertEquals('1234-5678-901234', $this->customer->sim_number);
        $this->assertEquals('pending', $this->customer->verification_status);
        $this->assertNull($this->customer->rejection_reason);

        // Verify stored in private local disk
        Storage::disk('local')->assertExists($this->customer->ktp_image);
        Storage::disk('local')->assertExists($this->customer->sim_image);

        // Verify NOT stored in public disk
        Storage::disk('public')->assertMissing($this->customer->ktp_image);
        Storage::disk('public')->assertMissing($this->customer->sim_image);
    }

    public function test_ktp_must_be_exactly_16_numeric_digits(): void
    {
        // 15 digits
        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '123456789012345',
            'sim_number' => 'SIM-12345',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $response->assertSessionHasErrors(['ktp_number']);

        // 17 digits
        $response2 = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '12345678901234567',
            'sim_number' => 'SIM-12345',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $response2->assertSessionHasErrors(['ktp_number']);

        // Contains letters
        $response3 = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '123456789012345A',
            'sim_number' => 'SIM-12345',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $response3->assertSessionHasErrors(['ktp_number']);
    }

    public function test_sim_number_is_required(): void
    {
        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => '',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $response->assertSessionHasErrors(['sim_number']);
    }

    public function test_uploaded_files_must_be_valid_images_under_2mb(): void
    {
        // Invalid file format (text file)
        $fakeTxt = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345',
            'ktp_image' => $fakeTxt,
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $response->assertSessionHasErrors(['ktp_image']);

        // File larger than 2MB (2048 KB)
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response2 = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => $largeFile,
        ]);
        $response2->assertSessionHasErrors(['sim_image']);
    }

    public function test_admin_can_view_customer_private_document(): void
    {
        $ktpPath = 'identities/fake_ktp.jpg';
        Storage::disk('local')->put($ktpPath, 'fake-image-content');

        $this->customer->update([
            'ktp_image' => $ktpPath,
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-9999',
        ]);

        $response = $this->actingAs($this->admin)->get(route('documents.identity.show', [
            'user' => $this->customer,
            'type' => 'ktp',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_customer_can_view_own_private_document(): void
    {
        $ktpPath = 'identities/fake_ktp.jpg';
        Storage::disk('local')->put($ktpPath, 'fake-image-content');

        $this->customer->update([
            'ktp_image' => $ktpPath,
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-9999',
        ]);

        $response = $this->actingAs($this->customer)->get(route('documents.identity.show', [
            'user' => $this->customer,
            'type' => 'ktp',
        ]));

        $response->assertOk();
    }

    public function test_other_customer_cannot_view_customer_private_document(): void
    {
        $ktpPath = 'identities/fake_ktp.jpg';
        Storage::disk('local')->put($ktpPath, 'fake-image-content');

        $this->customer->update(['ktp_image' => $ktpPath]);

        $response = $this->actingAs($this->otherCustomer)->get(route('documents.identity.show', [
            'user' => $this->customer,
            'type' => 'ktp',
        ]));

        $response->assertForbidden();
    }

    public function test_staff_cannot_view_customer_private_document(): void
    {
        $ktpPath = 'identities/fake_ktp.jpg';
        Storage::disk('local')->put($ktpPath, 'fake-image-content');

        $this->customer->update(['ktp_image' => $ktpPath]);

        $response = $this->actingAs($this->staff)->get(route('documents.identity.show', [
            'user' => $this->customer,
            'type' => 'ktp',
        ]));

        $response->assertForbidden();
    }

    public function test_admin_can_view_verifications_list_and_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.verifications.index'));
        $response->assertOk();
        $response->assertSee('Kelola Verifikasi Identitas Customer');
        $response->assertSee($this->customer->name);

        $responseFiltered = $this->actingAs($this->admin)->get(route('admin.verifications.index', ['status' => 'pending']));
        $responseFiltered->assertOk();
        $responseFiltered->assertSee($this->customer->name);
    }

    public function test_admin_can_approve_pending_customer_verification(): void
    {
        $ktpPath = 'identities/ktp_sample.jpg';
        $simPath = 'identities/sim_sample.jpg';
        Storage::disk('local')->put($ktpPath, 'ktp-bytes');
        Storage::disk('local')->put($simPath, 'sim-bytes');

        $this->customer->update([
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345678',
            'ktp_image' => $ktpPath,
            'sim_image' => $simPath,
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.verifications.approve', $this->customer));

        $response->assertRedirect(route('admin.verifications.show', $this->customer));
        $response->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals('verified', $this->customer->verification_status);
        $this->assertNotNull($this->customer->verified_at);
        $this->assertEquals($this->admin->id, $this->customer->verified_by);
        $this->assertTrue($this->customer->isVerified());
        $this->assertTrue($this->customer->canMakeReservation());
    }

    public function test_admin_cannot_approve_customer_without_complete_documents(): void
    {
        $this->customer->update([
            'ktp_number' => null,
            'sim_number' => null,
            'ktp_image' => null,
            'sim_image' => null,
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.verifications.approve', $this->customer));

        $response->assertSessionHas('error');
        $this->customer->refresh();
        $this->assertEquals('pending', $this->customer->verification_status);
        $this->assertFalse($this->customer->canMakeReservation());
    }

    public function test_admin_can_reject_verification_with_reason(): void
    {
        $this->customer->update([
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345678',
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.verifications.reject', $this->customer), [
            'rejection_reason' => 'Foto KTP tidak terbaca jelas dan buram.',
        ]);

        $response->assertRedirect(route('admin.verifications.show', $this->customer));
        $response->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals('rejected', $this->customer->verification_status);
        $this->assertEquals('Foto KTP tidak terbaca jelas dan buram.', $this->customer->rejection_reason);
        $this->assertNull($this->customer->verified_at);
        $this->assertFalse($this->customer->canMakeReservation());
    }

    public function test_admin_cannot_reject_verification_without_reason(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.verifications.reject', $this->customer), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors(['rejection_reason']);
        $this->customer->refresh();
        $this->assertEquals('pending', $this->customer->verification_status);
    }

    public function test_staff_cannot_approve_or_reject_verification(): void
    {
        $responseApprove = $this->actingAs($this->staff)->patch(route('admin.verifications.approve', $this->customer));
        $responseApprove->assertForbidden();

        $responseReject = $this->actingAs($this->staff)->patch(route('admin.verifications.reject', $this->customer), [
            'rejection_reason' => 'Alasan penolakan dari staf',
        ]);
        $responseReject->assertForbidden();
    }

    public function test_rejected_customer_can_resubmit_and_status_resets_to_pending(): void
    {
        $ktpPath = 'identities/old_ktp.jpg';
        $simPath = 'identities/old_sim.jpg';
        Storage::disk('local')->put($ktpPath, 'old-ktp');
        Storage::disk('local')->put($simPath, 'old-sim');

        $this->customer->update([
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345678',
            'ktp_image' => $ktpPath,
            'sim_image' => $simPath,
            'verification_status' => 'rejected',
            'rejection_reason' => 'Foto KTP buram',
        ]);

        $newKtpFile = UploadedFile::fake()->image('new_ktp.jpg');

        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345678',
            'ktp_image' => $newKtpFile,
        ]);

        $response->assertRedirect(route('customer.dashboard'));

        $this->customer->refresh();
        $this->assertEquals('pending', $this->customer->verification_status);
        $this->assertNull($this->customer->rejection_reason);
        $this->assertFalse($this->customer->canMakeReservation());
    }

    public function test_verified_customer_cannot_resubmit_documents(): void
    {
        $this->customer->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->customer)->post(route('customer.verification.store'), [
            'ktp_number' => '3578012304950001',
            'sim_number' => 'SIM-12345678',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_image' => UploadedFile::fake()->image('sim.jpg'),
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_can_make_reservation_business_rule_helper(): void
    {
        $user = new User(['verification_status' => 'pending']);
        $this->assertFalse($user->canMakeReservation());

        $user->verification_status = 'rejected';
        $this->assertFalse($user->canMakeReservation());

        $user->verification_status = 'verified';
        $this->assertTrue($user->canMakeReservation());
    }
}
