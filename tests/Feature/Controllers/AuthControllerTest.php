<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Validation fails on login endpoint.
     *
     * @return void
     */
    public function testLoginValidation()
    {
        $response = $this->json('POST', 'login');
        $response->assertStatus(422);
        $this->assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email', 'password']
            )
        );
    }

    /**
     * Login failed.
     *
     * @return void
     */
    public function testLoginIncorrect()
    {
        $response = $this->json('POST', 'login', [
            'email'    => 'fakeemail@fake.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)->assertJsonFragment(['error' => 'invalid_grant']);
    }

    /**
     * Login succeeded.
     *
     * @return void
     */
    public function testLoginSucceeded()
    {
        $user = [
            'email'    => Config::get('constants.seed.email'),
            'password' => Config::get('constants.seed.password'),
        ];

        $this->json('POST', 'register', array_merge($user, ['terms' => true]));

        $response = $this->json('POST', 'login', $user);

        $response->assertStatus(200);
        $this->assertTrue(Arr::has($response->json(), 'data.auth_token.access_token'));
        $this->assertTrue(
            Arr::get($response->json(), 'data.user.email') === Config::get('constants.seed.email')
        );
    }
}
