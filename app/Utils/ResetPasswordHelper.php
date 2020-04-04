<?php

namespace App\Utils;

use App\Models\User;
use App\Support\Traits\HandlesAuth;
use GuzzleHttp\Promise\Promise;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordHelper
{
    use HandlesAuth;

    /**
     * @var PasswordBroker
     */
    private PasswordBroker $passwordBroker;

    public function __construct(PasswordBroker $passwordBroker)
    {
        $this->passwordBroker = $passwordBroker;
    }

    /**
     * Encrypt email for forgot password url.
     */
    public function encryptEmail(string $email): string
    {
        return base64_encode($email);
    }

    /**
     * Decrypt email from forgot password url.
     */
    public function decryptEmail(string $email): string
    {
        return base64_decode($email);
    }

    /**
     * Working with promises here so we can get the user from the closure after the password
     * has been reset for use in authenticating on the next step.
     *
     * @param array $args - password, password_confirmation, token
     *
     * @return GuzzleHttp\Promise\Promise A promise that the password will be reset
     */
    public function passwordReset($args)
    {
        $promise = new Promise();

        $resetResult = $this->passwordBroker->reset($args, function ($user, $password) use (&$promise) {
            $user->password = bcrypt($password);
            $user->setRememberToken(Str::random(60));
            $user->save();

            event(new PasswordReset($user));
            $promise->resolve($user);
        });

        if (Password::PASSWORD_RESET != $resetResult) {
            $promise->reject(new \Error('reset_password_token_expired'));
        }

        return $promise;
    }

    /**
     * Give an email and token validate that the token is valid.
     *
     * @param array $args - contains
     */
    public function validateResetToken(array $args): bool
    {
        $user = User::where('email', $this->decryptEmail($args['emailHash']))->first();
        if (! $user) {
            return false;
        }

        return $this->passwordBroker->tokenExists($user, $args['token']);
    }
}
