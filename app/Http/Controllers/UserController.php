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
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\ValidateResetTokenRequest;
use App\Jobs\DeleteUser;
use App\Models\User;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $response = $user->toArray();
        $response['total_cards'] = $this->userRepo->getTotalCards($user);

        return response()->json(['data' => $response]);
    }

    public function update(UpdateUserRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $user = User::find($userId);

        if (! empty($request->get('old_password') && ! Hash::check($request->get('old_password'), $user->password))) {
            return response()->json(['error' => 'invalid_password', 'message' => 'The old password you entered was not correct.'], 400);
        }
        if ((! empty($request->get('old_password')) && empty($request->get('new_password'))) || (empty($request->get('old_password')) && ! empty($request->get('new_password')))) {
            return response()->json(['error' => 'bad_request', 'message' => 'If you are changing your password, you must include both your old password and your new password.'], 400);
        }
        if (! empty($request->get('email')) && $user->email !== $request->get('email') && User::where('email', $request->get('email'))->exists()) {
            return response()->json(['error' => 'email_exists', 'message' => 'The email you entered already exists. Please try again.'], 400);
        }

        $this->userRepo->update($user, $request->all());

        return response()->json($user);
    }

    public function delete(Request $request): JsonResponse
    {
        $user = $request->user();

        DeleteUser::dispatch($user);

        $user->properties = ['tagged_for_deletion' => true];
        // Change the email so the old email can be used again while we're waiting for the job to finish
        $user->email = 'deleting-'.$user->email;
        $user->save();

        return response()->json('success');
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
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
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
}
