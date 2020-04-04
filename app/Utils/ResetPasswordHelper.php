<?php

namespace App\Utils;

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
     * Working with promises here so we can get the user from the closure after the password
     * has been reset for use in authenticating on the next step.
     *
     * @param array $args an array containing new_password, and a token
     *
     * @return GuzzleHttp\Promise\Promise A promise that the password will be reset
     */
    public function passwordReset($args)
    {
        $promise = new Promise();

        $resetResult = app(PasswordBroker::class)->reset($args, function ($user, $password) use (&$promise) {
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
}
