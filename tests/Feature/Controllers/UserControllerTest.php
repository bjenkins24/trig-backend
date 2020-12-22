<?php

namespace Tests\Feature\Controllers;

use App\Jobs\SendMail;
use App\Jobs\SetupGoogleIntegration;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Modules\Card\Integrations\Google\GoogleConnection;
use App\Modules\User\Helpers\ResetPasswordHelper;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    private function getResetToken()
    {
        $user = User::where('email', Config::get('constants.seed.email'))->first();

        return app(PasswordBroker::class)->createToken($user);
    }

    private function encryptEmail($email)
    {
        $resetPasswordHelper = app(ResetPasswordHelper::class);

        return $resetPasswordHelper->encryptEmail($email);
    }

    private function assertLoggedIn($response, $email): void
    {
        self::assertTrue(Arr::has($response->json(), 'data.authToken.access_token'));
        self::assertSame(Arr::get($response->json(), 'data.user.email'), $email);
    }

    /**
     * Test validation on register endpoint.
     */
    public function testRegistrationValidation(): void
    {
        $response = $this->json('POST', 'register');
        $response->assertStatus(422);
        self::assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email', 'password', 'terms']
            )
        );
    }

    /**
     * See if you can register a new user.
     */
    public function testRegistrationSucceed(): void
    {
        $this->refreshDb();
        \Queue::fake();
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
        Queue::assertPushed(SendMail::class, 1);
    }

    /**
     * See if the user exists or not.
     */
    public function testRegistrationUserExists(): void
    {
        $this->refreshDb();
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
     */
    public function testMe(): void
    {
        $response = $this->client('GET', 'me');
        $response->assertStatus(200)->assertJsonFragment([
            'email'       => Config::get('constants.seed.email'),
            'first_name'  => Config::get('constants.seed.first_name'),
            'last_name'   => Config::get('constants.seed.last_name'),
            'id'          => 1,
            'total_cards' => 0,
        ]);
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testForgotPasswordValidate(): void
    {
        $params = [];
        $response = $this->json('POST', 'forgot-password', $params);
        $response->assertStatus(422);
        self::assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email']
            )
        );

        $params = ['email' => 'notanemail'];
        $response = $this->json('POST', 'forgot-password', $params);
        $response->assertStatus(422);
        self::assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['email']
            )
        );
    }

    /**
     * Test forgot password send email with hash.
     */
    public function testForgotPasswordUserDoesNotExist(): void
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
     */
    public function testForgotPasswordSendsMail(): void
    {
        Mail::fake();
        $params = [
            'email' => Config::get('constants.seed.email'),
        ];
        $response = $this->json('POST', 'forgot-password', $params);

        $response->assertStatus(200);

        Mail::assertSent(ForgotPasswordMail::class, function ($mail) {
            $emailHashExists = Str::contains(
                $mail->resetUrl,
                [
                    $this->encryptEmail(Config::get('constants.seed.email')),
                    $this->getResetToken(),
                ]
            );
            $hasCorrectName = $mail->to[0]['name'] === Config::get('constants.seed.first_name').' '.Config::get('constants.seed.last_name');

            return $mail->hasTo(Config::get('constants.seed.email')) && $emailHashExists && $hasCorrectName;
        });
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testResetPasswordValidate(): void
    {
        $params = [];
        $response = $this->json('POST', 'reset-password', $params);
        $response->assertStatus(422);
        self::assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['password', 'password_confirmation', 'token', 'email_hash']
            )
        );
    }

    /**
     * Test Reset password token expired.
     */
    public function testResetPasswordTokenExpired(): void
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(Config::get('constants.seed.email')),
        ];
        $this->json('POST', 'reset-password', $params);
        $this->json('POST', 'reset-password', $params)->
            assertJsonFragment(['error' => 'reset_password_token_expired']);
    }

    /**
     * Test Reset password token expired.
     */
    public function testResetPasswordSuccess(): void
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'reset-password', $params);
        $this->assertLoggedIn($response, Config::get('constants.seed.email'));
    }

    /**
     * Test fails on validation forgot password.
     */
    public function testValidateResetTokenValidation(): void
    {
        $params = [];
        $response = $this->json('POST', 'reset-password', $params);
        $response->assertStatus(422);
        self::assertTrue(
            Arr::has(
                Arr::get($response->json(), 'errors'),
                ['token', 'email_hash']
            )
        );
    }

    /**
     * Test Reset password token expired.
     */
    public function testValidateResetTokenInvalid(): void
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => 'Fake token',
            'email_hash'            => $this->encryptEmail(Config::get('constants.seed.email')),
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
     */
    public function testValidateResetTokenValid(): void
    {
        $password = 'myCoolNewPassword';
        $params = [
            'password'              => $password,
            'password_confirmation' => $password,
            'token'                 => $this->getResetToken(),
            'email_hash'            => $this->encryptEmail(Config::get('constants.seed.email')),
        ];
        $response = $this->json('POST', 'validate-reset-token', $params);
        $response->assertJsonFragment(['data' => 'valid']);
    }

    /**
     * Test login and register sso google.
     */
    public function testGoogleSso(): void
    {
        $this->refreshDb();
        \Queue::fake();
        $email = 'sam_sung@example.com';
        $this->partialMock(GoogleConnection::class, static function ($mock) use ($email) {
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

        \Queue::assertPushed(SetupGoogleIntegration::class, 1);
        $this->assertLoggedIn($response, $email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $response = $this->json('POST', 'google-sso', ['code' => 'ABCD123'])->assertStatus(200);
        \Queue::assertPushed(SetupGoogleIntegration::class, 1);
        $this->assertLoggedIn($response, $email);
    }

    /**
     * Test google sso failed.
     */
    public function testGoogleSsoFail(): void
    {
        \Queue::fake();
        $email = 'sam_sung@example.com';
        $this->partialMock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getUser')->andReturn([])->once();
        });

        $this->json('POST', 'google-sso', ['code' => 'ABCD123'])->assertStatus(200)->assertJsonFragment([
            'error' => 'auth_failed',
        ]);
    }
}
