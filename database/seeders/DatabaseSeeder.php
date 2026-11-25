<?php

namespace Database\Seeders;

use App\Models\RentalSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder demo hanya boleh dijalankan pada lingkungan lokal atau pengujian.');
        }

        // 1. Roles
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );

        $staffRole = Role::updateOrCreate(
            ['name' => 'staff'],
            ['display_name' => 'Staf Operasional']
        );

        $customerRole = Role::updateOrCreate(
            ['name' => 'customer'],
            ['display_name' => 'Pelanggan']
        );

        // 2. Users (Password configurable via DEMO_USER_PASSWORD env variable)
        $defaultPassword = env('DEMO_USER_PASSWORD', 'password123');

        User::updateOrCreate(
            ['email' => 'admin@driveease.test'],
            [
                'role_id' => $adminRole->id,
                'name' => 'Admin DriveEase',
                'password' => Hash::make($defaultPassword),
                'phone' => '081234567890',
                'address' => 'Jl. Sudirman No. 10, Jakarta',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@driveease.test'],
            [
                'role_id' => $staffRole->id,
                'name' => 'Staf Operasional',
                'password' => Hash::make($defaultPassword),
                'phone' => '081234567891',
                'address' => 'Jl. Thamrin No. 5, Jakarta',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@driveease.test'],
            [
                'role_id' => $customerRole->id,
                'name' => 'Budi Santoso',
                'password' => Hash::make($defaultPassword),
                'phone' => '081234567892',
                'address' => 'Jl. Merdeka No. 12, Bandung',
                'email_verified_at' => now(),
            ]
        );

        // 3. Rental Business Settings (default row only if not yet configured)
        // insertOrIgnore() hanya menyisipkan baris ID 1 apabila belum ada di tabel
        // rental_settings, sehingga perubahan pengaturan oleh admin tetap terjaga.
        $defaultPublicInfo = RentalSetting::defaultPublicInfo();
        DB::table('rental_settings')->insertOrIgnore([
            'id' => 1,
            'business_name' => $defaultPublicInfo->business_name,
            'services' => $defaultPublicInfo->services,
            'address' => $defaultPublicInfo->address,
            'contact' => $defaultPublicInfo->contact,
            'operating_hours' => $defaultPublicInfo->operating_hours,
            'terms' => $defaultPublicInfo->terms,
            'bank_name' => 'Bank Simulasi DriveEase',
            'account_number' => '123456789012',
            'account_holder' => 'PT DriveEase Simulasi',
        ]);

        // 4. Car Types & Reference Fleet (Turo)
        $this->call(TuroFleetSeeder::class);
    }
}
