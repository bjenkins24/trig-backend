<?php

namespace Tests\Feature\Controllers;

use Illuminate\Support\Arr;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    /**
     * See if the user exists or not.
     *
     * @return void
     */
    public function testRegistrationValidation()
    {
        $response = $this->json('POST', 'register');
        $response->assertStatus(422);
        $this->assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email', 'password', 'terms']
            )
        );
    }
}
