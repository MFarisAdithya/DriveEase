<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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

        // 3. Car Types
        $mpv = CarType::updateOrCreate(
            ['name' => 'MPV'],
            ['description' => 'Multi Purpose Vehicle nyaman untuk keluarga dan rombongan']
        );

        $suv = CarType::updateOrCreate(
            ['name' => 'SUV'],
            ['description' => 'Sport Utility Vehicle tangguh untuk segala medan perkotaan']
        );

        $sedan = CarType::updateOrCreate(
            ['name' => 'Sedan'],
            ['description' => 'Kendaraan elegan dengan kenyamanan berkendara maksimal']
        );

        $cityCar = CarType::updateOrCreate(
            ['name' => 'City Car'],
            ['description' => 'Mobil kompak lincah dan sangat hemat bahan bakar']
        );

        // 4. Sample Cars
        Car::updateOrCreate(
            ['plate_number' => 'B 1234 EAA'],
            [
                'car_type_id' => $mpv->id,
                'name' => 'Toyota Avanza 1.5 G',
                'brand' => 'Toyota',
                'model_year' => 2023,
                'transmission' => 'manual',
                'capacity' => 7,
                'price_per_day' => 400000,
                'status' => 'available',
                'description' => 'Kondisi prima, AC double blower, irit dan terawat rutin.',
            ]
        );

        Car::updateOrCreate(
            ['plate_number' => 'B 2345 FAB'],
            [
                'car_type_id' => $mpv->id,
                'name' => 'Toyota Innova Zenix 2.0 V',
                'brand' => 'Toyota',
                'model_year' => 2024,
                'transmission' => 'automatic',
                'capacity' => 7,
                'price_per_day' => 650000,
                'status' => 'available',
                'description' => 'Kabin sangat senyap, suspensi empuk, fitur keselamatan canggih.',
            ]
        );

        Car::updateOrCreate(
            ['plate_number' => 'B 3456 GAC'],
            [
                'car_type_id' => $suv->id,
                'name' => 'Honda HR-V 1.5 SE',
                'brand' => 'Honda',
                'model_year' => 2023,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 550000,
                'status' => 'rented',
                'description' => 'Desain sporty dan modern, panoramic roof, audio mantap.',
            ]
        );

        Car::updateOrCreate(
            ['plate_number' => 'B 4567 HAD'],
            [
                'car_type_id' => $sedan->id,
                'name' => 'Honda Civic RS',
                'brand' => 'Honda',
                'model_year' => 2024,
                'transmission' => 'automatic',
                'capacity' => 5,
                'price_per_day' => 900000,
                'status' => 'available',
                'description' => 'Mesin turbo responsif, interior premium, audio Bose.',
            ]
        );

        Car::updateOrCreate(
            ['plate_number' => 'B 5678 IAE'],
            [
                'car_type_id' => $cityCar->id,
                'name' => 'Daihatsu Ayla 1.2 R',
                'brand' => 'Daihatsu',
                'model_year' => 2022,
                'transmission' => 'manual',
                'capacity' => 5,
                'price_per_day' => 300000,
                'status' => 'maintenance',
                'description' => 'Sedang dalam pengecekan rem berkala di bengkel resmi.',
            ]
        );
    }
}
