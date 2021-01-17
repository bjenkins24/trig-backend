<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\IntegrationTest;

class DashboardControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testItCanBrowseMainDashboard()
    {
        $response = $this->withExceptionHandling()
            ->getJson('/nova-api/dashboards/main')
            ->assertOk()
            ->assertJson([
                'label' => 'Dashboard',
                'cards' => [],
            ]);
    }

    public function testItCantBrowseInvalidDashboard()
    {
        $response = $this->withExceptionHandling()
            ->getJson('/nova-api/dashboards/foobar')
            ->assertStatus(404);
    }
}
