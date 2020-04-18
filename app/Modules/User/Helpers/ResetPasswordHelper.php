<?php

namespace App\Modules\User\Helpers;

use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use GuzzleHttp\Promise\Promise;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;

class ResetPasswordHelper
{
    use HandlesAuth;

    private UserRepository $user;
    private PasswordBroker $passwordBroker;

    public function __construct(PasswordBroker $passwordBroker, UserRepository $user)
    {
        $this->passwordBroker = $passwordBroker;
        $this->user = $user;
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
     * Decrypt the email_hash and unset it, so we have proper args
     * for login.
     */
    public function getPasswordResetArgs(array $args): array
    {
        $args['email'] = $this->decryptEmail($args['email_hash']);
        unset($args['email_hash']);

        return $args;
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
            $user->setRememberToken(\Str::random(60));
            $user->save();

            event(new PasswordReset($user));
            $promise->resolve($user);
        });

        if (\Password::PASSWORD_RESET !== $resetResult) {
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
        $user = $this->user->findByEmail($this->decryptEmail($args['email_hash']));
        if (! $user) {
            return false;
        }

        return $this->passwordBroker->tokenExists($user, $args['token']);
    }

    public function sendForgotPasswordNotification(User $user)
    {
        $token = $this->passwordBroker->createToken($user);
        $userFullName = app(UserService::class)->getName($user);

        // and set the name prop, because Mail needs it
        $user->name = $userFullName;

        $emailHash = $this->encryptEmail($user->email);

        // send the email
        return \Mail::to($user)->send(new ForgotPasswordMail($token, $emailHash));
    }
}
