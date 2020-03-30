<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation on register endpoint.
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

    /**
     * See if the user exists or not.
     *
     * @return void
     */
    public function testRegistrationUserExists()
    {
        $params = [
            'email'    => 'test@example.com',
            'password' => 'password123',
            'terms'    => true,
        ];
        $userExistsJson = ['error' => 'user_exists'];
        $response = $this->json('POST', 'register', $params);
        $response->assertStatus(201)->assertJsonMissing($userExistsJson);

        $response = $this->json('POST', 'register', $params);
        $response->assertStatus(200)->assertJsonFragment($userExistsJson);
    }

    /**
     * Test the me endpoint.
     *
     * @return void
     */
    public function testMe()
    {
        $response = $this->client('GET', 'me');
        $response->assertStatus(200)->assertJsonFragment([
            'email'      => Config::get('constants.seed.email'),
            'first_name' => null,
            'last_name'  => null,
            'id'         => 1,
        ]);
    }
}
