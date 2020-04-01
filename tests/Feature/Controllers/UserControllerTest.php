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
    public function testRegistrationSucceedAndUserExists()
    {
        $params = [
            'email'    => Config::get('constants.seed.email'),
            'password' => Config::get('constants.seed.password'),
            'terms'    => true,
        ];
        $userExistsJson = ['error' => 'user_exists'];
        $response = $this->json('POST', 'register', $params);
        $response->assertStatus(201)->assertJsonMissing($userExistsJson);

        $this->assertTrue(Arr::has($response->json(), 'data.auth_token.access_token'));
        $this->assertTrue(
            Arr::get($response->json(), 'data.user.email') === Config::get('constants.seed.email')
        );

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
