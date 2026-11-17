<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CatalogNavigationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detail_preserves_search_filters_and_pagination_in_return_link(): void
    {
        $car = Car::firstOrFail();
        $filters = ['search' => 'Honda', 'car_type_id' => (string) $car->car_type_id, 'transmission' => 'automatic', 'page' => '2'];
        $this->get(route('cars.show', ['car' => $car] + $filters))
            ->assertOk()->assertSee(e(route('cars.index', $filters)), false);
    }

    public function test_detail_without_filters_returns_to_plain_catalog(): void
    {
        $this->get(route('cars.show', Car::firstOrFail()))->assertOk()->assertSee('href="'.route('cars.index').'"', false);
    }

    public function test_customer_mobile_menu_exposes_account_and_identity_destinations(): void
    {
        $user = User::where('email', 'customer@driveease.test')->firstOrFail();
        $this->actingAs($user)->get(route('cars.index'))->assertOk()
            ->assertSee('data-account-menu', false)->assertSee('Navigasi akun')
            ->assertSee(route('customer.verification.create'))->assertSee('Dasbor saya');
    }

    public function test_default_validation_messages_are_indonesian(): void
    {
        $this->post('/register', ['name' => str_repeat('a', 256), 'email' => 'test@example.test', 'phone' => '08123456789', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrors(['name' => 'Nama maksimal 255 karakter.']);
    }
}
