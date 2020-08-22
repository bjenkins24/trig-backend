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
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * @throws UserExists
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if ($this->userRepo->findByEmail($request->get('email'))) {
            throw new UserExists('The user already exists.');
        }

        $user = $this->userService->create($request->all());

        // Login the new user
        $authToken = $this->authRequest($request->all());

        return response()->json(['data' => compact('authToken', 'user')], 201);
    }

    /**
     * Initiate the forgot password process.
     *
     * @throws NoUserFound
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = $this->userRepo->findByEmail($request->get('email'));

        if (! $user) {
            throw new NoUserFound('No user was found');
        }

        $this->userService->resetPassword->sendForgotPasswordNotification($user);

        return response()->json([
            'data' => 'success',
        ]);
    }

    /**
     * Reset the password.
     *
     * @throws ResetPasswordTokenExpired
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $args = $this->userService->resetPassword->getPasswordResetArgs($request->all());

        try {
            $user = $this->userService->resetPassword->passwordReset($args)->wait();
        } catch (Error $e) {
            throw new ResetPasswordTokenExpired('Reset password token has expired');
        }

        // Login the new user
        $authToken = $this->authRequest($args);

        return response()->json(['data' => compact('authToken', 'user')], 200);
    }

    /**
     * Validate that a given reset token is valid.
     */
    public function validateResetToken(ValidateResetTokenRequest $request): JsonResponse
    {
        $isValidToken = $this->userService->resetPassword->validateResetToken($request->all()) ?
            'valid' : 'invalid';

        return response()->json(['data' => $isValidToken]);
    }

    /**
     * @throws FailedGoogleSso
     * @throws OauthMissingTokens
     * @throws OauthIntegrationNotFound
     */
    public function googleSso(GoogleSsoRequest $request): JsonResponse
    {
        $response = $this->oauthIntegrationService->makeConnectionIntegration('google')->getUser($request->get('code'));
        if (! $response) {
            throw new FailedGoogleSso('Could not SSO with Google');
        }

        $user = $this->userRepo->findByEmail($response['payload']->get('email'));
        $status = 200;
        if ($user) {
            // Login user that exists
            $authToken['access_token'] = $this->userService->getAccessToken($user);
        } else {
            $authParams = [
                'email'    => $response['payload']->get('email'),
                'password' => Str::random(24),
            ];
            $user = $this->userService->createFromGoogle($authParams, $response['oauthCredentials']);

            // Login the new user
            $authToken = $this->authRequest($authParams);
            $status = 201;
        }

        return response()->json(['data' => compact('authToken', 'user')], $status);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testGoogle(Request $request): JsonResponse
    {
        $user = $request->user();
        $files = $this->oauthIntegrationService->makeCardIntegration('google')->getFiles($user, strtotime('-20 days'));

        return response()->json(['data' => $files]);
    }
}
