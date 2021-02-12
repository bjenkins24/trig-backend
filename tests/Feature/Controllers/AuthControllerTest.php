<?php

namespace Tests\Feature\Controllers;

use App\Http\Controllers\AuthController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    /**
     * Validation fails on login endpoint.
     */
    public function testLoginValidation(): void
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
     */
    public function testLoginIncorrect(): void
    {
        $response = $this->json('POST', 'login', [
            'email'    => 'fakeemail@fake.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)->assertJsonFragment(['error' => 'invalid_grant']);
    }

    /**
     * Login succeeded.
     */
    public function testLoginSucceeded(): void
    {
        $user = [
            'email'    => Config::get('constants.seed.email'),
            'password' => Config::get('constants.seed.password'),
        ];

        $response = $this->json('POST', 'login', $user);

        $response->assertStatus(200);
        self::assertTrue(Arr::has($response->json(), 'data.authToken.access_token'));
        self::assertSame(
            Config::get('constants.seed.email'), Arr::get($response->json(), 'data.user.email')
        );
    }

    /**
     * No access token returned.
     */
    public function testLoginNoAccessToken(): void
    {
        $this->partialMock(AuthController::class, function ($mock) {
            $mock->shouldReceive('authRequest')->andReturn([])->once();
        });

        $user = [
            'email'    => Config::get('constants.seed.email'),
            'password' => Config::get('constants.seed.password'),
        ];

        $this->json('POST', 'login', $user)->assertJsonFragment(['error' => 'no_access_token']);
    }
}
