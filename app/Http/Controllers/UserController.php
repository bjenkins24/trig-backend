<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use App\Utils\ResetPasswordHelper;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesAuth;

    /**
     * @var UserService
     */
    private UserService $user;

    /**
     * @var ResetPasswordHelper
     */
    private ResetPasswordHelper $resetPasswordHelper;

    public function __construct(
        UserService $user,
        ResetPasswordHelper $resetPasswordHelper
    ) {
        $this->user = $user;
        $this->resetPasswordHelper = $resetPasswordHelper;
    }

    /**
     * Return the logged in user.
     */
    public function me(Request $request)
    {
        return $request->user();
    }

    /**
     * Register a new user account.
     *
     * @return void
     */
    public function register(Request $request)
    {
        $rules = [
            'email'     => 'required',
            'password'  => 'required',
            'terms'     => 'required',
        ];
        $request->validate($rules);

        if (User::where('email', $request->get('email'))->exists()) {
            return response()->json([
               'error' => 'user_exists', 'message' => 'The email you tried to register already exists',
            ], 200);
        }

        $user = $this->user->createAccount($request->all());

        // Login the new user
        $auth_token = $this->authRequest($request->all());

        return response()->json(['data' => compact('auth_token', 'user')], 201);
    }

    /**
     * Initiate the forgot password process.
     *
     * @return bool
     */
    public function forgotPassword(Request $request)
    {
        $rules = [
            'email'     => 'required',
        ];
        $request->validate($rules);

        $user = User::where('email', $request->get('email'))->first();

        if (! $user) {
            return response()->json([
                'error'   => 'no_user_found',
                'message' => 'There was no user with the given email. Please try again.',
            ]);
        }

        $token = app(PasswordBroker::class)->createToken($user);
        $user->sendPasswordResetNotification($token);

        return response()->json([
            'data' => 'success',
        ]);
    }

    /**
     * Reset the password.
     *
     * @return void
     */
    public function resetPassword(Request $request)
    {
        $rules = [
            'password'                  => 'required',
            'password_confirmation'     => 'required',
            'token'                     => 'required',
        ];
        $request->validate($rules);

        try {
            $user = $this->resetPasswordHelper->passwordReset($request->all())->wait();
        } catch (\Error $e) {
            return response()->json([
                'error'   => 'reset_password_token_expired',
                'message' => 'The password reset link has expired.',
            ], 400);
        }

        // Login the new user
        $auth_token = $this->authRequest($request->all());

        return response()->json(['data' => compact('auth_token', 'user')], 200);
    }

    /**
     * Validate that a given reset token is valid.
     *
     * @return void
     */
    public function validateResetToken(Request $request)
    {
        $rules = [
            'token'                  => 'required',
            'emailHash'              => 'required',
        ];
        $request->validate($rules);

        $isValidToken = $this->resetPasswordHelper->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }
}
