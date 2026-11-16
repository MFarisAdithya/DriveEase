<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_shows_landing_page_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sewa Kendaraan');
        $response->assertSee('Lihat Kendaraan');
    }
}
