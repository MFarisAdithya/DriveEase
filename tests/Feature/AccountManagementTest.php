<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_account_page_updates_the_signed_in_profile_and_password_for_each_role(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            $account = User::factory()->create([
                'role_id' => Role::where('name', $role)->firstOrFail()->id,
                'verification_status' => 'pending',
            ]);

            $this->actingAs($account)->get(route('account.edit'))->assertOk()->assertSee('Keluar Sekarang');
            $profile = [
                'name' => 'Nama Diperbarui',
                'email' => strtoupper($account->email),
                'phone' => null,
                'address' => 'Jl. Pengujian No. 88',
                'role_id' => 999,
                'verification_status' => 'verified',
            ];
            if ($role === 'customer') {
                $this->patch(route('account.profile'), $profile)->assertSessionHasErrors('phone');
                $profile['phone'] = '081234567890';
            }
            $this->patch(route('account.profile'), $profile)->assertSessionHasNoErrors();

            $freshAccount = $account->fresh();
            $this->assertSame('Nama Diperbarui', $freshAccount->name);
            $this->assertSame($profile['phone'], $freshAccount->phone);
            $this->assertSame('Jl. Pengujian No. 88', $freshAccount->address);
            $this->assertSame($role, $freshAccount->role->name);
            $this->assertSame('pending', $freshAccount->verification_status);

            $password = [
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'sandi-baru-123',
            ];
            $this->patch(route('account.password'), $password + ['current_password' => 'salah'])
                ->assertSessionHasErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
            $this->patch(route('account.password'), $password + ['current_password' => 'password'])
                ->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check($password['password'], $account->fresh()->password));

            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->get(route('account.edit'))->assertRedirect(route('login'));
        }
    }

    public function test_login_regenerates_session_id_and_logout_invalidates_session(): void
    {
        $customer = User::factory()->create([
            'role_id' => Role::where('name', 'customer')->firstOrFail()->id,
            'password' => 'password123',
        ]);

        $this->get(route('login'))->assertOk();
        $beforeLoginSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.dashboard'));

        $afterLoginSessionId = session()->getId();
        $this->assertNotSame($beforeLoginSessionId, $afterLoginSessionId);
        $this->assertAuthenticatedAs($customer);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($afterLoginSessionId, session()->getId());
    }

    public function test_admin_can_manage_staff_and_deactivation_blocks_old_sessions(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->firstOrFail()->id]);
        $customer = User::factory()->create(['role_id' => Role::where('name', 'customer')->firstOrFail()->id]);

        $this->actingAs($customer)->get(route('admin.staff.index'))->assertForbidden();
        $this->post(route('admin.staff.store'), ['name' => 'Ilegal'])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $profile = ['name' => 'Staf Baru', 'email' => 'staf-baru@example.com', 'phone' => '081234567890'];
        $this->post(route('admin.staff.store'), $profile + [
            'password' => 'kata-sandi-123',
            'password_confirmation' => 'kata-sandi-123',
        ])->assertSessionHasNoErrors();

        $staff = User::where('email', $profile['email'])->firstOrFail();
        $this->assertTrue($staff->isStaff());
        $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.staff.edit', $staff))->assertOk();
        $this->patch(route('admin.staff.update', $staff), array_replace($profile, ['name' => 'Staf Diubah']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Staf Diubah', $staff->fresh()->name);

        $this->get(route('admin.staff.edit', $customer))->assertNotFound();
        $this->patch(route('admin.staff.deactivate', $admin))->assertNotFound();
        $this->patch(route('admin.staff.deactivate', $customer))->assertNotFound();

        DB::table('sessions')->insert([
            'id' => 'sess_'.$staff->id.'_'.uniqid(),
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $originalSessionDriver = config('session.driver');
        config(['session.driver' => 'database']);
        try {
            $this->patch(route('admin.staff.deactivate', $staff))->assertRedirect(route('admin.staff.index'));
        } finally {
            config(['session.driver' => $originalSessionDriver]);
        }
        $this->assertTrue($staff->fresh()->isDeactivated());
        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);

        // Instance model yang baru mencerminkan status basis data yang akan dibaca
        // guard dari ID sesi lama ketika staf membuat permintaan berikutnya.
        $this->actingAs($staff->fresh())->get(route('staff.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login'), ['email' => $profile['email'], 'password' => 'kata-sandi-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_archived_car_stays_in_customer_history_and_leaves_public_catalog(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->firstOrFail()->id]);
        $customer = User::factory()->create(['role_id' => Role::where('name', 'customer')->firstOrFail()->id]);
        $vehicle = [
            'car_type_id' => CarType::firstOrFail()->id,
            'name' => 'Mobil Uji Arsip',
            'brand' => 'DriveEase',
            'plate_number' => 'BE 7777 UJI',
            'model_year' => 2024,
            'transmission' => 'automatic',
            'capacity' => 4,
            'price_per_day' => 250000,
            'status' => 'maintenance',
        ];
        $this->actingAs($admin)->post(route('admin.cars.store'), $vehicle)->assertSessionHasErrors('maintenance_reason');
        $this->post(route('admin.cars.store'), $vehicle + ['maintenance_reason' => 'Periksa rem'])->assertSessionHasNoErrors();
        $car = Car::where('plate_number', $vehicle['plate_number'])->firstOrFail();
        $this->assertSame('Periksa rem', $car->maintenance_reason);
        $vehicle['status'] = 'available';
        $this->put(route('admin.cars.update', $car), $vehicle)->assertSessionHasNoErrors();
        $this->assertNull($car->fresh()->maintenance_reason);

        // Seed satu histori pada tabel rental sebelum alur pemesanan tersedia.
        DB::table('rentals')->insert([
            'rental_code' => 'TEST-ARSIP',
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'start_date' => today()->subDays(2),
            'end_date' => today()->subDay(),
            'total_days' => 1,
            'daily_price' => 250000,
            'total_amount' => 250000,
            'rental_status' => 'returned',
            'payment_status' => 'paid',
        ]);
        $this->delete(route('admin.cars.destroy', $car))->assertRedirect(route('admin.cars.index'));
        $this->assertNotNull($car->fresh()->archived_at);
        $this->assertDatabaseHas('rentals', ['rental_code' => 'TEST-ARSIP', 'car_id' => $car->id]);
        $this->get(route('admin.verifications.show', $customer))->assertOk()->assertSee('TEST-ARSIP');
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee($car->name);
        $this->post(route('logout'));
        $this->get(route('cars.show', $car))->assertNotFound();
        $this->get(route('cars.index', ['search' => $car->name]))->assertOk()->assertDontSee(route('cars.show', $car));

        $staff = User::factory()->create(['role_id' => Role::where('name', 'staff')->firstOrFail()->id]);
        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk()->assertDontSee($car->name);
    }

    public function test_public_home_shows_rental_information_without_bank_details(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->firstOrFail()->id]);
        $settings = [
            'business_name' => 'Rental Uji Lampung',
            'services' => 'Sewa mobil harian',
            'address' => 'Jl. Uji Bandar Lampung',
            'contact' => '081234567890',
            'operating_hours' => '08.00–18.00',
            'terms' => 'Bawa dokumen asli.',
            'bank_name' => 'Bank Simulasi',
            'account_number' => '000123456789',
            'account_holder' => 'Rental Uji',
        ];
        $this->actingAs($admin)->get(route('admin.rental-settings.edit'))->assertOk();
        $this->patch(route('admin.rental-settings.update'), $settings)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rental_settings', ['id' => 1] + $settings);

        $invalidSettings = array_replace($settings, ['account_number' => 'nomor tidak valid']);
        $this->patch(route('admin.rental-settings.update'), $invalidSettings)
            ->assertSessionHasErrors(['account_number' => 'Nomor rekening hanya boleh berisi angka tanpa spasi atau tanda baca.']);
        $this->assertDatabaseHas('rental_settings', ['id' => 1] + $settings);

        // Pastikan model RentalSetting menyembunyikan rincian rekening saat diserialisasi.
        $settingModel = RentalSetting::current();
        $this->assertNotNull($settingModel);
        $this->assertArrayNotHasKey('account_number', $settingModel->toArray());
        $this->assertArrayNotHasKey('bank_name', $settingModel->toArray());
        $this->assertArrayNotHasKey('account_holder', $settingModel->toArray());

        $customer = User::factory()->create(['role_id' => Role::where('name', 'customer')->firstOrFail()->id]);
        $this->actingAs($customer)->get(route('admin.rental-settings.edit'))->assertForbidden();
        $this->patch(route('admin.rental-settings.update'), $settings)->assertForbidden();

        $staff = User::factory()->create(['role_id' => Role::where('name', 'staff')->firstOrFail()->id]);
        $this->actingAs($staff)->get(route('admin.rental-settings.edit'))->assertForbidden();
        $this->patch(route('admin.rental-settings.update'), $settings)->assertForbidden();

        $this->post(route('logout'));
        $this->get(route('home'))->assertOk()
            ->assertSee($settings['business_name'])
            ->assertSee($settings['services'])
            ->assertSee($settings['address'])
            ->assertSee($settings['contact'])
            ->assertSee($settings['operating_hours'])
            ->assertSee($settings['terms'])
            ->assertDontSee($settings['account_number']);
    }

    public function test_public_home_shows_default_business_info_when_settings_table_is_empty(): void
    {
        DB::table('rental_settings')->delete();
        $defaultInfo = RentalSetting::defaultPublicInfo();

        $this->get(route('home'))->assertOk()
            ->assertSee($defaultInfo->business_name)
            ->assertSee($defaultInfo->address)
            ->assertSee($defaultInfo->contact)
            ->assertSee($defaultInfo->operating_hours);
    }

    public function test_identity_numbers_are_encrypted_in_the_database(): void
    {
        $account = User::factory()->create([
            'role_id' => Role::where('name', 'customer')->firstOrFail()->id,
            'ktp_number' => '1234567890123456',
            'sim_number' => '12345678901234',
        ])->fresh();

        // getRawOriginal() memeriksa nilai mentah sebelum accessor dan cast.
        $this->assertNotSame($account->ktp_number, $account->getRawOriginal('ktp_number'));
        $this->assertNotSame($account->nik_ktp, $account->getRawOriginal('nik_ktp'));
        $this->assertNotSame($account->sim_number, $account->getRawOriginal('sim_number'));
        $this->assertSame('1234567890123456', $account->ktp_number);
        $this->assertSame('1234567890123456', $account->nik_ktp);
        $this->assertSame('12345678901234', $account->sim_number);
        $this->assertArrayNotHasKey('ktp_number', $account->toArray());
    }

    public function test_a_guest_can_reset_the_password_once_using_the_emailed_token(): void
    {
        Notification::fake();
        $account = User::factory()->create([
            'role_id' => Role::where('name', 'customer')->firstOrFail()->id,
        ]);

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => $account->email])->assertSessionHas('success');
        Notification::assertSentTo($account, ResetPassword::class);

        // sent() mengambil notifikasi yang ditangkap Laravel tanpa mengirim email nyata.
        // first() mengambil notifikasi pertama agar token di formulir berasal dari broker.
        $token = Notification::sent($account, ResetPassword::class)->first()->token;
        $this->get(route('password.reset', ['token' => $token, 'email' => $account->email]))->assertOk();

        $payload = [
            'email' => $account->email,
            'password' => 'sandi-reset-123',
            'password_confirmation' => 'sandi-reset-123',
        ];

        $this->post(route('password.update'), $payload + ['token' => 'invalid'])
            ->assertSessionHasErrors('email');
        $this->post(route('password.update'), $payload + ['token' => $token])
            ->assertRedirect(route('login'));
        $this->post(route('password.update'), $payload + ['token' => $token])
            ->assertSessionHasErrors('email');

        $this->post(route('login'), ['email' => $account->email, 'password' => $payload['password']])
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_password_reset_renders_indonesian_email_and_blocks_deactivated_staff(): void
    {
        $staff = User::factory()->create([
            'role_id' => Role::where('name', 'staff')->firstOrFail()->id,
        ]);

        // Pastikan email reset benar-benar dirender melalui mailer array berbahasa Indonesia.
        $this->post(route('password.email'), ['email' => $staff->email])
            ->assertSessionHas('success');

        $sentMessages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sentMessages);
        $rawEmail = $sentMessages->first()->toString();
        $this->assertStringContainsString('Tautan Pemulihan Kata Sandi - DriveEase', $rawEmail);
        $this->assertStringContainsString('Buat Kata Sandi Baru', $rawEmail);
        $this->assertStringContainsString('reset-password/', $rawEmail);

        // Nonaktifkan staf setelah token lama terbit, lalu pastikan permintaan baru
        // maupun pemakaian token lama ditolak.
        $preDeactivationToken = Password::broker()->createToken($staff);
        $staff->deactivated_at = now();
        $staff->save();

        Notification::fake();
        $this->post(route('password.email'), ['email' => $staff->email])
            ->assertSessionHas('success');
        Notification::assertNothingSentTo($staff);

        $this->post(route('password.update'), [
            'token' => $preDeactivationToken,
            'email' => $staff->email,
            'password' => 'sandi-ilegal-123',
            'password_confirmation' => 'sandi-ilegal-123',
        ])->assertSessionHasErrors('email');
    }
}
