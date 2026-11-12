<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriveEaseProgress1ContinuationTest extends TestCase
{
    protected function getAdminUser(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->first();
    }

    protected function getStaffUser(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'staff'))->first();
    }

    protected function getCustomerUser(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'customer'))->first();
    }

    // ==========================================
    // 1. VEHICLE CATEGORY (car_types) TESTS
    // ==========================================

    public function test_admin_can_view_car_types_list(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.car-types.index'));
        $response->assertStatus(200);
        $response->assertSee('Kelola Kategori Kendaraan');
        $response->assertSee('SUV');
        $response->assertSee('MPV');
    }

    public function test_admin_can_create_car_type(): void
    {
        $admin = $this->getAdminUser();
        $uniqueName = 'Convertible ' . rand(100, 999);

        $response = $this->actingAs($admin)->post(route('admin.car-types.store'), [
            'name' => $uniqueName,
            'description' => 'Mobil atap terbuka mewah dan bergaya.',
        ]);

        $response->assertRedirect(route('admin.car-types.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('car_types', [
            'name' => $uniqueName,
        ]);
    }

    public function test_validation_rejects_duplicate_car_type_name(): void
    {
        $admin = $this->getAdminUser();
        $existing = CarType::first();

        $response = $this->actingAs($admin)->post(route('admin.car-types.store'), [
            'name' => $existing->name,
            'description' => 'Mencoba duplikasi',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_can_edit_car_type(): void
    {
        $admin = $this->getAdminUser();
        $type = CarType::create([
            'name' => 'Type to Edit ' . rand(100, 999),
            'description' => 'Deskripsi lama',
        ]);

        $updatedDescription = 'Deskripsi diperbarui oleh test';

        $response = $this->actingAs($admin)->put(route('admin.car-types.update', $type), [
            'name' => $type->name,
            'description' => $updatedDescription,
        ]);

        $response->assertRedirect(route('admin.car-types.index'));
        $response->assertSessionHas('success');

        $type->refresh();
        $this->assertEquals($updatedDescription, $type->description);
    }

    public function test_category_assigned_to_vehicles_cannot_be_deleted(): void
    {
        $admin = $this->getAdminUser();
        // MPV is used by sample cars
        $usedCategory = CarType::whereHas('cars')->first();

        $response = $this->actingAs($admin)->delete(route('admin.car-types.destroy', $usedCategory));

        $response->assertRedirect(route('admin.car-types.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('car_types', [
            'id' => $usedCategory->id,
        ]);
    }

    public function test_admin_can_delete_unused_category(): void
    {
        $admin = $this->getAdminUser();
        $unusedCategory = CarType::create([
            'name' => 'Unused Type ' . rand(100, 999),
            'description' => 'Tidak dipakai mobil manapun',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.car-types.destroy', $unusedCategory));

        $response->assertRedirect(route('admin.car-types.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('car_types', [
            'id' => $unusedCategory->id,
        ]);
    }

    public function test_non_admin_cannot_access_car_type_management(): void
    {
        $staff = $this->getStaffUser();
        $customer = $this->getCustomerUser();

        $this->actingAs($staff)->get(route('admin.car-types.index'))->assertStatus(403);
        $this->actingAs($customer)->get(route('admin.car-types.index'))->assertStatus(403);
    }

    // ==========================================
    // 2. DYNAMIC VEHICLE CATALOG TESTS
    // ==========================================

    public function test_public_visitor_can_view_vehicle_catalog(): void
    {
        $response = $this->get(route('cars.index'));
        $response->assertStatus(200);
        $response->assertSee('Katalog kendaraan');
        $response->assertSee('Bandingkan kapasitas');
    }

    public function test_vehicle_catalog_search_filters_correctly(): void
    {
        $car = Car::first();

        $response = $this->get(route('cars.index', ['search' => $car->brand]));
        $response->assertStatus(200);
        $response->assertSee($car->name);

        $emptyResponse = $this->get(route('cars.index', ['search' => 'NonExistentBrandXYZ123']));
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('Tidak ada armada yang sesuai');
    }

    public function test_vehicle_catalog_category_filtering(): void
    {
        $category = CarType::whereHas('cars')->first();

        $response = $this->get(route('cars.index', ['car_type_id' => $category->id]));
        $response->assertStatus(200);
        $response->assertSee($category->name);
    }

    public function test_public_visitor_can_view_vehicle_detail_page(): void
    {
        $car = Car::first();

        $response = $this->get(route('cars.show', $car));
        $response->assertStatus(200);
        $response->assertSee($car->name);
        $response->assertSee($car->plate_number);
        $response->assertSee('Spesifikasi Kendaraan');
        $response->assertSee('Tarif Sewa Harian');
    }

    // ==========================================
    // 3. BASIC CUSTOMER REGISTRATION TESTS
    // ==========================================

    public function test_guest_can_view_registration_page(): void
    {
        $response = $this->get(route('register'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Akun Pelanggan');
    }

    public function test_guest_can_register_as_customer(): void
    {
        $uniqueEmail = 'pelanggan' . rand(1000, 9999) . '@test.com';

        $response = $this->post(route('register'), [
            'name' => 'Pelanggan Baru DriveEase',
            'email' => $uniqueEmail,
            'phone' => '089876543210',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => $uniqueEmail,
            'name' => 'Pelanggan Baru DriveEase',
            'verification_status' => 'pending',
        ]);

        $newUser = User::where('email', $uniqueEmail)->first();
        $this->assertTrue($newUser->isCustomer());
        $this->assertFalse($newUser->isAdmin());
        $this->assertTrue(Hash::check('secret1234', $newUser->password));
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $existing = User::first();

        $response = $this->post(route('register'), [
            'name' => 'Another User',
            'email' => $existing->email,
            'phone' => '081111111111',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Mismatched Password User',
            'email' => 'mismatch' . rand(100, 999) . '@test.com',
            'phone' => '082222222222',
            'password' => 'password888',
            'password_confirmation' => 'different888',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
