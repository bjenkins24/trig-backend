<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ForgotPassword;
use App\Http\Requests\User\GoogleSso;
use App\Http\Requests\User\Register;
use App\Http\Requests\User\ResetPassword;
use App\Http\Requests\User\ValidateResetToken;
use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\Card\Integrations\Google;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use App\Utils\ResetPasswordHelper;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use HandlesAuth;

    /**
     * @var UserService
     */
    private UserService $user;

    /**
     * @var OauthConnectionService
     */
    private OauthConnectionService $oauthConnection;

    /**
     * @var ResetPasswordHelper
     */
    private ResetPasswordHelper $resetPasswordHelper;

    public function __construct(
        UserService $user,
        ResetPasswordHelper $resetPasswordHelper,
        OauthConnectionService $oauthConnection
    ) {
        $this->user = $user;
        $this->resetPasswordHelper = $resetPasswordHelper;
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
    public function register(Register $request)
    {
        if (User::where('email', $request->get('email'))->exists()) {
            return response()->json([
               'error' => 'user_exists', 'message' => 'The email you tried to register already exists',
            ], 200);
        }

        $user = $this->user->createAccount($request->all());

        // Login the new user
        $authToken = $this->authRequest($request->all());

        return response()->json(['data' => compact('authToken', 'user')], 201);
    }

    /**
     * Initiate the forgot password process.
     *
     * @return bool
     */
    public function forgotPassword(ForgotPassword $request)
    {
        $user = User::where('email', $request->get('email'))->first();

        if (! $user) {
            return response()->json([
                'error'   => 'no_user_found',
                'message' => 'There is no user with the given email. Please try again.',
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
    public function resetPassword(ResetPassword $request)
    {
        $args = $request->all();
        $args['email'] = $this->resetPasswordHelper->decryptEmail($request->get('email_hash'));
        unset($args['email_hash']);

        try {
            $user = $this->resetPasswordHelper->passwordReset($args)->wait();
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
    public function validateResetToken(ValidateResetToken $request)
    {
        $isValidToken = $this->resetPasswordHelper->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }

    public function googleSso(GoogleSso $request)
    {
        $response = $this->oauthConnection->makeIntegration('google')->getUser($request->get('code'));
        if (! $response) {
            return response()->json(['error' => 'auth_failed', 'message' => 'Something went wrong. You were not able to be authenticated']);
        }

        $user = User::where('email', $response['payload']->get('email'))->first();
        $status = 200;
        if ($user) {
            // Login user that exists
            $authToken['access_token'] = $user->createToken('trig')->accessToken;
        } else {
            // Create a new user
            $authParams = [
                'email'    => $response['payload']->get('email'),
                'password' => Str::random(16),
            ];
            $user = $this->user->createAccount($authParams);
            $result = $this->oauthConnection->storeConnection($user, Google::getKey(), $response['oauthCredentials']);

            SyncCards::dispatch($user, Google::getKey());

            // Login the new user
            $authToken = $this->authRequest($authParams);
            $status = 201;
        }

        return response()->json(['data' => compact('authToken', 'user')], $status);
    }
}
