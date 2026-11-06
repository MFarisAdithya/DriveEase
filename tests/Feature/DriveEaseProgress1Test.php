<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriveEaseProgress1Test extends TestCase
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
        return User::where('email', 'customer@driveease.test')->first() 
            ?? User::whereHas('role', fn ($q) => $q->where('name', 'customer'))->first();
    }

    /**
     * Test 1: Guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));

        $adminResponse = $this->get('/admin/dashboard');
        $adminResponse->assertRedirect(route('login'));
    }

    /**
     * Test 2: Login page can be rendered.
     */
    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('DriveEase');
    }

    /**
     * Test 3: Admin login redirects to admin dashboard.
     */
    public function test_admin_can_login_and_reach_admin_dashboard(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $dashboardResponse = $this->actingAs($admin)->get('/admin/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Dashboard Ringkasan');
    }

    /**
     * Test 4: Staff login redirects to staff dashboard.
     */
    public function test_staff_can_login_and_reach_staff_dashboard(): void
    {
        $staff = $this->getStaffUser();

        $response = $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff);

        $dashboardResponse = $this->actingAs($staff)->get('/staff/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Staf Operasional');
    }

    /**
     * Test 5: Customer login redirects to customer dashboard.
     */
    public function test_customer_can_login_and_reach_customer_dashboard(): void
    {
        $customer = $this->getCustomerUser();

        $response = $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer);

        $dashboardResponse = $this->actingAs($customer)->get('/customer/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Armada Siap Disewa');
    }

    /**
     * Test 6: Customer cannot access admin routes (HTTP 403).
     */
    public function test_customer_cannot_access_admin_routes(): void
    {
        $customer = $this->getCustomerUser();

        $response = $this->actingAs($customer)->get('/admin/dashboard');
        $response->assertStatus(403);

        $carListResponse = $this->actingAs($customer)->get('/admin/cars');
        $carListResponse->assertStatus(403);
    }

    /**
     * Test 7: Staff cannot modify or create vehicles (HTTP 403 on admin routes).
     */
    public function test_staff_cannot_access_admin_vehicle_management(): void
    {
        $staff = $this->getStaffUser();

        $response = $this->actingAs($staff)->get('/admin/cars');
        $response->assertStatus(403);

        $createResponse = $this->actingAs($staff)->get('/admin/cars/create');
        $createResponse->assertStatus(403);

        $storeResponse = $this->actingAs($staff)->post('/admin/cars', [
            'name' => 'Illegal Car',
        ]);
        $storeResponse->assertStatus(403);
    }

    /**
     * Test 8: Admin can view vehicle index and create a vehicle.
     */
    public function test_admin_can_create_vehicle(): void
    {
        $admin = $this->getAdminUser();
        $carType = CarType::first();
        $testPlate = 'B ' . rand(1000, 9999) . ' TST';

        $response = $this->actingAs($admin)->post('/admin/cars', [
            'name' => 'Mitsubishi Xpander Cross Test',
            'car_type_id' => $carType->id,
            'brand' => 'Mitsubishi',
            'plate_number' => $testPlate,
            'model_year' => 2023,
            'transmission' => 'automatic',
            'capacity' => 7,
            'price_per_day' => 450000,
            'status' => 'available',
            'description' => 'Unit uji coba fitur otomatis.',
        ]);

        $response->assertRedirect(route('admin.cars.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cars', [
            'plate_number' => $testPlate,
            'name' => 'Mitsubishi Xpander Cross Test',
        ]);
    }

    /**
     * Test 9: Admin can view single vehicle details.
     */
    public function test_admin_can_view_vehicle_details(): void
    {
        $admin = $this->getAdminUser();
        $car = Car::first();

        $response = $this->actingAs($admin)->get(route('admin.cars.show', $car));
        $response->assertStatus(200);
        $response->assertSee($car->name);
        $response->assertSee($car->plate_number);
    }

    /**
     * Test 10: Admin can update vehicle data.
     */
    public function test_admin_can_update_vehicle(): void
    {
        $admin = $this->getAdminUser();
        $car = Car::first();

        $updatedPrice = 525000;

        $response = $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'name' => $car->name,
            'car_type_id' => $car->car_type_id,
            'brand' => $car->brand,
            'plate_number' => $car->plate_number,
            'model_year' => $car->model_year,
            'transmission' => $car->transmission,
            'capacity' => $car->capacity,
            'price_per_day' => $updatedPrice,
            'status' => 'rented',
            'description' => 'Pembaruan tarif dan status oleh test.',
        ]);

        $response->assertRedirect(route('admin.cars.index'));
        $response->assertSessionHas('success');

        $car->refresh();
        $this->assertEquals($updatedPrice, (int) $car->price_per_day);
        $this->assertEquals('rented', $car->status);
    }

    /**
     * Test 11: Validation rejects duplicate plate numbers.
     */
    public function test_validation_rejects_duplicate_plate_numbers(): void
    {
        $admin = $this->getAdminUser();
        $existingCar = Car::first();

        $response = $this->actingAs($admin)->post('/admin/cars', [
            'name' => 'Duplicate Plate Car',
            'car_type_id' => $existingCar->car_type_id,
            'brand' => 'Honda',
            'plate_number' => $existingCar->plate_number,
            'model_year' => 2023,
            'transmission' => 'automatic',
            'capacity' => 5,
            'price_per_day' => 500000,
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors(['plate_number']);
    }

    /**
     * Test 12: Admin can delete a vehicle.
     */
    public function test_admin_can_delete_vehicle(): void
    {
        $admin = $this->getAdminUser();
        $carType = CarType::first();

        $carToDelete = Car::create([
            'name' => 'Temporary Car for Deletion',
            'car_type_id' => $carType->id,
            'brand' => 'Suzuki',
            'plate_number' => 'B ' . rand(1000, 9999) . ' DEL',
            'model_year' => 2021,
            'transmission' => 'manual',
            'capacity' => 5,
            'price_per_day' => 300000,
            'status' => 'available',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.cars.destroy', $carToDelete));

        $response->assertRedirect(route('admin.cars.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('cars', [
            'id' => $carToDelete->id,
        ]);
    }
}
