<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VerificationQueue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return User::where('email', 'customer@driveease.test')->firstOrFail();
    }

    private function payload(): array
    {
        return ['ktp_number' => '3578012304950001', 'sim_number' => '12345678901234'];
    }

    public function test_replacement_failure_keeps_old_documents_and_database(): void
    {
        $customer = $this->customer();
        Storage::put('identities/old-ktp.jpg', 'old');
        Storage::put('identities/old-sim.jpg', 'old');
        $customer->update($this->payload() + ['ktp_image' => 'identities/old-ktp.jpg', 'sim_image' => 'identities/old-sim.jpg']);
        $disk = Storage::disk('local');
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('copy')->once()->andReturn(false);
        Storage::set('local', $mock);
        $this->actingAs($customer)->post(route('customer.verification.store'), $this->payload() + ['ktp_image' => UploadedFile::fake()->image('new.jpg')])->assertSessionHas('error');
        $this->assertSame('identities/old-ktp.jpg', $customer->fresh()->ktp_image);
        $disk->assertExists('identities/old-ktp.jpg');
        $disk->assertExists('identities/old-sim.jpg');
        $this->assertCount(2, $disk->allFiles('identities'));
    }

    public function test_database_failure_cleans_new_files_and_preserves_temporary_upload(): void
    {
        $customer = $this->customer();
        Storage::put('identities_temp/ktp.jpg', 'ktp');
        Storage::put('identities_temp/sim.jpg', 'sim');
        $this->withSession(['temp_ktp_image' => 'identities_temp/ktp.jpg', 'temp_sim_image' => 'identities_temp/sim.jpg']);
        $dispatcher = User::getEventDispatcher();
        $isolated = clone $dispatcher;
        $isolated->listen('eloquent.saving: '.User::class, function (): void {
            throw new RuntimeException('Simulated save failure');
        });
        User::setEventDispatcher($isolated);
        try {
            $this->actingAs($customer)->post(route('customer.verification.store'), $this->payload())->assertSessionHas('error');
        } finally {
            User::setEventDispatcher($dispatcher);
        }
        $this->assertNull($customer->fresh()->ktp_image);
        $this->assertSame([], Storage::allFiles('identities'));
        Storage::assertExists('identities_temp/ktp.jpg');
    }

    public function test_success_cleans_temporary_files(): void
    {
        $this->actingAs($this->customer())->post(route('customer.verification.store'), $this->payload() + ['ktp_image' => UploadedFile::fake()->image('ktp.jpg'), 'sim_image' => UploadedFile::fake()->image('sim.jpg')])->assertSessionHas('success');
        $this->assertSame([], Storage::allFiles('identities_temp'));
        $this->assertCount(2, Storage::allFiles('identities'));
    }

    public function test_replacing_invalid_submission_cleans_previous_temporary_file(): void
    {
        $this->actingAs($this->customer());
        $this->post(route('customer.verification.store'), ['ktp_image' => UploadedFile::fake()->image('first.jpg')])->assertSessionHasErrors();
        $old = session('temp_ktp_image');
        $this->post(route('customer.verification.store'), ['ktp_image' => UploadedFile::fake()->image('second.jpg')])->assertSessionHasErrors();
        Storage::assertMissing($old);
        Storage::assertExists(session('temp_ktp_image'));
    }

    public function test_cleanup_only_removes_expired_temporary_files(): void
    {
        Storage::put('identities_temp/expired.jpg', 'old');
        Storage::put('identities_temp/current.jpg', 'new');
        Storage::put('identities/permanent.jpg', 'keep');
        touch(Storage::path('identities_temp/expired.jpg'), now()->subHours(49)->timestamp);
        $this->artisan('identities:prune-temporary')->assertSuccessful();
        Storage::assertMissing('identities_temp/expired.jpg');
        Storage::assertExists('identities_temp/current.jpg');
        Storage::assertExists('identities/permanent.jpg');
    }

    public function test_missing_files_cannot_be_approved_and_are_not_ready(): void
    {
        $customer = $this->customer();
        $customer->update($this->payload() + ['ktp_image' => 'identities/missing.jpg', 'sim_image' => 'identities/missing-sim.jpg']);
        $queue = app(VerificationQueue::class)->queries();
        $this->assertFalse($queue['ready']->whereKey($customer->id)->exists());
        $this->assertTrue($queue['incomplete']->whereKey($customer->id)->exists());
        $admin = User::where('email', 'admin@driveease.test')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.verifications.approve', $customer))->assertSessionHas('error');
        $this->assertSame('pending', $customer->fresh()->verification_status);
    }

    public function test_dashboard_and_queue_use_same_ready_count(): void
    {
        $customer = $this->customer();
        Storage::put('identities/ktp.jpg', 'ktp');
        Storage::put('identities/sim.jpg', 'sim');
        $customer->update($this->payload() + ['ktp_image' => 'identities/ktp.jpg', 'sim_image' => 'identities/sim.jpg']);
        $admin = User::where('email', 'admin@driveease.test')->firstOrFail();
        $count = app(VerificationQueue::class)->counts()['ready'];
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertViewHas('stats', fn ($stats) => $stats['pending_verifications'] === $count);
        $this->get(route('admin.verifications.index', ['status' => 'ready']))->assertViewHas('customers', fn ($customers) => $customers->total() === $count);
    }

    public function test_login_is_limited_and_recovers_after_wait(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'customer@driveease.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'customer@driveease.test', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'CUSTOMER@DRIVEEASE.TEST', 'password' => 'password123'])->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_registration_rejects_case_variant_of_existing_email(): void
    {
        $this->post('/register', ['name' => 'Tester', 'email' => ' CUSTOMER@DRIVEEASE.TEST ', 'phone' => '08123456789', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('email');
    }

    public function test_production_hides_and_blocks_demo_accounts(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'production');
        $this->get('/login')->assertDontSee('password123');
        $this->post('/login', ['email' => 'admin@driveease.test', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
