<?php

namespace Tests\Feature\Controllers;

use App\Mail\ForgotPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
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
     * See if you can register a new user.
     *
     * @return void
     */
    public function testRegistrationSucceed()
    {
        $email = 'sam_sung@example.com';
        $params = [
            'email'    => $email,
            'password' => 'mycoolnewpassword',
            'terms'    => true,
        ];
        $userExistsJson = ['error' => 'user_exists'];
        $response = $this->json('POST', 'register', $params);
        $response->assertStatus(201)->assertJsonMissing($userExistsJson);

        $this->assertTrue(Arr::has($response->json(), 'data.auth_token.access_token'));
        $this->assertTrue(
            Arr::get($response->json(), 'data.user.email') === $email
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
            'email'    => Config::get('constants.seed.email'),
            'password' => Config::get('constants.seed.password'),
            'terms'    => true,
        ];
        $userExistsJson = ['error' => 'user_exists'];
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
            'first_name' => Config::get('constants.seed.first_name'),
            'last_name'  => Config::get('constants.seed.last_name'),
            'id'         => 1,
        ]);
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testForgotPasswordValidate()
    {
        $params = [];
        $response = $this->json('POST', 'forgot-password', $params);
        $response->assertStatus(422);
        $this->assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email']
            )
        );

        $params = ['email' => 'notanemail'];
        $response = $this->json('POST', 'forgot-password', $params);
        $response->assertStatus(422);
        $this->assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email']
            )
        );
    }

    /**
     * Test forgot password send email with hash.
     *
     * @return void
     */
    public function testForgotPasswordUserDoesNotExist()
    {
        $params = [
            'email' => 'user@doesntexist.com',
        ];
        $response = $this->json('POST', 'forgot-password', $params);
        $noUserFoundJson = ['error' => 'no_user_found'];

        $response->assertStatus(200)->assertJsonFragment($noUserFoundJson);
    }

    /**
     * Test forgot password send email with hash.
     *
     * @return void
     */
    public function testForgotPasswordSendsMail()
    {
        Mail::fake();
        $params = [
            'email' => Config::get('constants.seed.email'),
        ];
        $response = $this->json('POST', 'forgot-password', $params);

        $response->assertStatus(200);
        Mail::assertSent(ForgotPasswordMail::class);
    }
}
