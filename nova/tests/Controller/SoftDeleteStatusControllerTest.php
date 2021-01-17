<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\IntegrationTest;

class SoftDeleteStatusControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanDetermineIfResourceSoftDeletes()
    {
        // With soft deletes...
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/soft-deletes');

        $response->assertStatus(200);
        $response->assertJson([
            'softDeletes' => true,
        ]);

        // Without soft deletes...
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/soft-deletes');

        $response->assertStatus(200);
        $response->assertJson([
            'softDeletes' => false,
        ]);
    }
}
