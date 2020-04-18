<?php

namespace Tests\Feature\Controllers;

use App\Jobs\SyncCards;
use App\Mail\ForgotPasswordMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Modules\OauthConnection\Connections\Google;
use App\Modules\User\Helpers\ResetPasswordHelper;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function getResetToken()
    {
        $user = User::where('email', \Config::get('constants.seed.email'))->first();

        return app(PasswordBroker::class)->createToken($user);
    }

    private function encryptEmail($email)
    {
        $resetPasswordHelper = app(ResetPasswordHelper::class);

        return $resetPasswordHelper->encryptEmail($email);
    }

    private function assertLoggedIn($response, $email)
    {
        $this->assertTrue(\Arr::has($response->json(), 'data.authToken.access_token'));
        $this->assertTrue(
            \Arr::get($response->json(), 'data.user.email') === $email
        );
    }

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
            \Arr::has(
                \Arr::get($response->json(), 'errors'),
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
        \Mail::fake();
        $email = 'sam_sung@example.com';
        $params = [
            'email'    => $email,
            'password' => 'mycoolnewpassword',
            'terms'    => true,
        ];
        $userExistsJson = ['error' => 'user_exists'];
        $response = $this->json('POST', 'register', $params);

        $response->assertStatus(201)->assertJsonMissing($userExistsJson);
        $this->assertLoggedIn($response, $email);
        \Mail::assertSent(WelcomeMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    /**
     * See if the user exists or not.
     *
     * @return void
     */
    public function testRegistrationUserExists()
    {
        $params = [
            'email'    => \Config::get('constants.seed.email'),
            'password' => \Config::get('constants.seed.password'),
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
            'email'      => \Config::get('constants.seed.email'),
            'first_name' => \Config::get('constants.seed.first_name'),
            'last_name'  => \Config::get('constants.seed.last_name'),
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
            \Arr::has(
                \Arr::get($response->json(), 'errors'),
                ['email']
            )
        );

        $params = ['email' => 'notanemail'];
        $response = $this->json('POST', 'forgot-password', $params);
        $response->assertStatus(422);
        $this->assertTrue(
            \Arr::has(
                \Arr::get($response->json(), 'errors'),
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
        \Mail::fake();
        $params = [
            'email' => \Config::get('constants.seed.email'),
        ];
        $response = $this->json('POST', 'forgot-password', $params);

        $response->assertStatus(200);
        \Mail::assertSent(ForgotPasswordMail::class, function ($mail) {
            $emailHashExists = \Str::contains(
                $mail->resetUrl,
                [
                    $this->encryptEmail(\Config::get('constants.seed.email')),
                    $this->getResetToken(),
                ]
            );
            $hasCorrectName = $mail->to[0]['name'] === \Config::get('constants.seed.first_name').' '.\Config::get('constants.seed.last_name');

            return $mail->hasTo(\Config::get('constants.seed.email')) && $emailHashExists && $hasCorrectName;
        });
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testResetPasswordValidate()
    {
        $params = [];
        $response = $this->json('POST', 'reset-password', $params);
        $response->assertStatus(422);
        $this->assertTrue(
            \Arr::has(
                \Arr::get($response->json(), 'errors'),
                ['password', 'password_confirmation', 'token', 'email_hash']
            )
        );
    }

    /**
     * Test Reset password token expired.
     *
     * @return void
     */
    public function testResetPasswordTokenExpired()
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(\Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'reset-password', $params);
        $response = $this->json('POST', 'reset-password', $params)->
            assertJsonFragment(['error' => 'reset_password_token_expired']);
    }

    /**
     * Test Reset password token expired.
     *
     * @return void
     */
    public function testResetPasswordSuccess()
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(\Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'reset-password', $params);
        $this->assertLoggedIn($response, \Config::get('constants.seed.email'));
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testValidateResetTokenValidation()
    {
        $params = [];
        $response = $this->json('POST', 'reset-password', $params);
        $response->assertStatus(422);
        $this->assertTrue(
            \Arr::has(
                \Arr::get($response->json(), 'errors'),
                ['token', 'email_hash']
            )
        );
    }

    /**
     * Test Reset password token expired.
     *
     * @return void
     */
    public function testValidateResetTokenInvalid()
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => 'Fake token',
            'email_hash'            => $this->encryptEmail(\Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'validate-reset-token', $params);
        $response->assertJsonFragment(['data' => 'invalid']);

        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => 'Fake hash',
        ];
        $response = $this->json('POST', 'validate-reset-token', $params);
        $response->assertJsonFragment(['data' => 'invalid']);
    }

    /**
     * Test Reset password token expired.
     *
     * @return void
     */
    public function testValidateResetTokenValid()
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(\Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'validate-reset-token', $params);
        $response->assertJsonFragment(['data' => 'valid']);
    }

    /**
     * Test login and register sso google.
     *
     * @return void
     */
    public function testGoogleSso()
    {
        \Queue::fake();
        $email = 'sam_sung@example.com';
        $this->partialMock(Google::class, function ($mock) use ($email) {
            $mock->shouldReceive('getUser')->andReturn([
                'payload'          => collect(['email' => $email]),
                'oauthCredentials' => collect([
                    'access_token'  => '123',
                    'refresh_token' => '456',
                    'expires_in'    => 0,
                ]),
            ])->twice();
        });
        $response = $this->json('POST', 'google-sso', ['code' => 'ABCD123'])->assertStatus(201);

        \Queue::assertPushed(SyncCards::class, 1);
        $this->assertLoggedIn($response, $email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $response = $this->json('POST', 'google-sso', ['code' => 'ABCD123'])->assertStatus(200);
        \Queue::assertPushed(SyncCards::class, 1);
        $this->assertLoggedIn($response, $email);
    }

    /**
     * Test google sso failed.
     *
     * @return void
     */
    public function testGoogleSsoFail()
    {
        \Queue::fake();
        $email = 'sam_sung@example.com';
        $this->partialMock(Google::class, function ($mock) {
            $mock->shouldReceive('getUser')->andReturn([])->once();
        });

        $this->json('POST', 'google-sso', ['code' => 'ABCD123'])->assertStatus(200)->assertJsonFragment([
            'error' => 'auth_failed',
        ]);
    }
}
