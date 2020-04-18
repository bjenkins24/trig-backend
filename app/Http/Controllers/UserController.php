<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ForgotPasswordRequest;
use App\Http\Requests\User\GoogleSsoRequest;
use App\Http\Requests\User\RegisterRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\ValidateResetTokenRequest;
use App\Models\User;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use HandlesAuth;

    private UserService $user;
    private OauthConnectionService $oauthConnection;

    public function __construct(
        UserService $user,
        OauthConnectionService $oauthConnection
    ) {
        $this->user = $user;
        $this->oauthConnection = $oauthConnection;
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
    public function register(RegisterRequest $request)
    {
        if ($this->user->findByEmail($request->get('email'))) {
            return response()->json([
               'error' => 'user_exists', 'message' => 'The email you tried to register already exists',
            ], 200);
        }

        $user = $this->user->create($request->all());

        // Login the new user
        $authToken = $this->authRequest($request->all());

        return response()->json(['data' => compact('authToken', 'user')], 201);
    }

    /**
     * Initiate the forgot password process.
     *
     * @return bool
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $user = $this->user->findByEmail($request->get('email'));

        if (! $user) {
            return response()->json([
                'error'   => 'no_user_found',
                'message' => 'There is no user with the given email. Please try again.',
            ]);
        }

        $this->user->resetPassword->sendForgotPasswordNotification($user);

        return response()->json([
            'data' => 'success',
        ]);
    }

    /**
     * Reset the password.
     *
     * @return void
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $args = $this->user->resetPassword->getPasswordResetArgs($request->all());

        try {
            $user = $this->user->resetPassword->passwordReset($args)->wait();
        } catch (\Error $e) {
            return response()->json([
                'error'   => 'reset_password_token_expired',
                'message' => 'The password reset link has expired.',
            ], 400);
        }

        // Login the new user
        $authToken = $this->authRequest($args);

        return response()->json(['data' => compact('authToken', 'user')], 200);
    }

    /**
     * Validate that a given reset token is valid.
     *
     * @return void
     */
    public function validateResetToken(ValidateResetTokenRequest $request)
    {
        $isValidToken = $this->user->resetPassword->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }

    public function googleSso(GoogleSsoRequest $request)
    {
        $response = $this->oauthConnection->makeIntegration('google')->getUser($request->get('code'));
        if (! $response) {
            Log::notice('Unable to SSO user. Either Google is down or possibly a malicious user posted an invalid auth code.');

            return response()->json(['error' => 'auth_failed', 'message' => 'Something went wrong. You were not able to be authenticated']);
        }

        $user = $this->user->findByEmail($response['payload']->get('email'));
        $status = 200;
        if ($user) {
            // Login user that exists
            $authToken['access_token'] = $this->user->getAccessToken($user);
        } else {
            $authParams = [
                'email'    => $response['payload']->get('email'),
                'password' => Str::random(16),
            ];
            $user = $this->user->createFromGoogle($authParams, $response['oauthCredentials']);

            // Login the new user
            $authToken = $this->authRequest($authParams);
            $status = 201;
        }

        return response()->json(['data' => compact('authToken', 'user')], $status);
    }
}
