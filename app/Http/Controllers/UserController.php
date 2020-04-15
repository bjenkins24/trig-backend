<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Card\CardService;
use App\Modules\Card\Integrations\Google;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use App\Utils\ResetPasswordHelper;
use Google_Client as GoogleClient;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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
    public function register(Request $request)
    {
        $rules = [
            'email'     => 'required|email',
            'password'  => 'required',
            'terms'     => 'required|boolean',
        ];
        $request->validate($rules);

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
    public function forgotPassword(Request $request)
    {
        $rules = [
            'email'     => 'required|email',
        ];
        $request->validate($rules);

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
    public function resetPassword(Request $request)
    {
        $rules = [
            'password'                  => 'required',
            'password_confirmation'     => 'required',
            'token'                     => 'required',
            'email_hash'                => 'required',
        ];
        $request->validate($rules);

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
    public function validateResetToken(Request $request)
    {
        $rules = [
            'token'                   => 'required',
            'email_hash'              => 'required',
        ];
        $request->validate($rules);

        $isValidToken = $this->resetPasswordHelper->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }

    public function google(Request $request)
    {
        $oauthCredentials = $this->oauthConnection->getAccessToken('google', $request->get('code'));
        $client = new GoogleClient(['client_id' => Config::get('services.google.client_id')]);
        $payload = $client->verifyIdToken($oauthCredentials->get('id_token'));
        if (! $payload) {
            return response()->json(['error' => 'fail!']);
        }

        $user = User::where('email', $payload['email'])->first();
        $status = 200;
        if ($user) {
            $authToken['access_token'] = $user->createToken('trig')->accessToken;
        } else {
            // Create a new user
            $authParams = [
                'email'    => $payload['email'],
                'password' => Str::random(16),
            ];
            $user = $this->user->createAccount($authParams);
            $this->oauthConnection->storeConnection($user, Google::getKey(), $oauthCredentials);

            app(CardService::class)->syncAllIntegrations($user);

            // Login the new user
            $authToken = $this->authRequest($authParams);
            $status = 201;
        }

        return response()->json(['data' => compact('authToken', 'user')], $status);
    }
}
