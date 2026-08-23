<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 2 foundation tests only. No Patient/Doctor/Facility/etc. module
 * tests belong here yet — those come with their respective modules.
 * These prove the routing layer, Blade rendering, and base layout work.
 */
class FoundationTest extends TestCase
{
    public function test_welcome_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('MediConnect India');
    }

    public function test_api_ping_returns_ok(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
    }

    public function test_health_check_route_responds(): void
    {
        // Registered via ->withRouting(health: '/up') in bootstrap/app.php.
        $response = $this->get('/up');

        $response->assertOk();
    }
}
