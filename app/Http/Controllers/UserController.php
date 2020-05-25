<?php

namespace App\Http\Controllers;

use App\Exceptions\User\FailedGoogleSso;
use App\Exceptions\User\NoUserFound;
use App\Exceptions\User\ResetPasswordTokenExpired;
use App\Exceptions\User\UserExists;
use App\Http\Requests\User\ForgotPasswordRequest;
use App\Http\Requests\User\GoogleSsoRequest;
use App\Http\Requests\User\RegisterRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\ValidateResetTokenRequest;
use App\Models\Card;
use App\Models\User;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesAuth;

    private UserService $userService;
    private UserRepository $userRepo;
    private OauthIntegrationService $oauthIntegrationService;

    public function __construct(
        UserService $userService,
        UserRepository $userRepo,
        OauthIntegrationService $oauthIntegrationService
    ) {
        $this->userService = $userService;
        $this->userRepo = $userRepo;
        $this->oauthIntegrationService = $oauthIntegrationService;
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
        if ($this->userRepo->findByEmail($request->get('email'))) {
            throw new UserExists();
        }

        $user = $this->userService->create($request->all());

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
        $user = $this->userRepo->findByEmail($request->get('email'));

        if (! $user) {
            throw new NoUserFound();
        }

        $this->userService->resetPassword->sendForgotPasswordNotification($user);

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
        $args = $this->userService->resetPassword->getPasswordResetArgs($request->all());

        try {
            $user = $this->userService->resetPassword->passwordReset($args)->wait();
        } catch (\Error $e) {
            throw new ResetPasswordTokenExpired();
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
        $isValidToken = $this->userService->resetPassword->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }

    public function googleSso(GoogleSsoRequest $request)
    {
        $response = $this->oauthIntegrationService->makeConnectionIntegration('google')->getUser($request->get('code'));
        if (! $response) {
            throw new FailedGoogleSso();
        }

        $user = $this->userRepo->findByEmail($response['payload']->get('email'));
        $status = 200;
        if ($user) {
            // Login user that exists
            $authToken['access_token'] = $this->userService->getAccessToken($user);
        } else {
            $authParams = [
                'email'    => $response['payload']->get('email'),
                'password' => \Str::random(24),
            ];
            $user = $this->userService->createFromGoogle($authParams, $response['oauthCredentials']);

            // Login the new user
            $authToken = $this->authRequest($authParams);
            $status = 201;
        }

        return response()->json(['data' => compact('authToken', 'user')], $status);
    }

    public function testGoogle(Request $request)
    {
        $card = Card::find(6);
        $files = $this->oauthIntegrationService->makeCardIntegration('google')->saveCardData($card);

        return response()->json(['data' => $files]);
    }
}
