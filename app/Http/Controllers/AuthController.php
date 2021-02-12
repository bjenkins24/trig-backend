<?php

namespace App\Http\Controllers;

use App\Exceptions\Auth\NoAccessTokenSet;
use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Modules\User\ImpersonationService;
use App\Modules\User\UserRepository;
use App\Support\Traits\HandlesAuth;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    use HandlesAuth;

    private UserRepository $userRepo;
    private ImpersonationService $impersonationService;

    public function __construct(
        UserRepository $userRepo,
        ImpersonationService $impersonationService
    ) {
        $this->userRepo = $userRepo;
        $this->impersonationService = $impersonationService;
    }

    /**
     * Log in user.
     *
     * @throws NoAccessTokenSet
     * @throws Exception
     */
    public function login(Login $request): JsonResponse
    {
        try {
            $authToken = $this->authRequest($request->all());
        } catch (HttpException $e) {
            $error = $e->getMessage();
            $message = 'invalid_grant' === $error ?
                'The email or password you entered was incorrect. Please try again.' :
                'Something went wrong. Please try again';

            return response()->json([
                'error'   => $error,
                'message' => $message,
            ]);
        }

        $token = Arr::get($authToken, 'access_token');
        if (empty($token)) {
            throw new NoAccessTokenSet('No access token set');
        }

        $user = $this->userRepo->getMe($this->userRepo->findByEmail($request->get('email')));

        return response()
            ->json(['data' => compact('authToken', 'user')], 200)
            ->withCookie(cookie()->forever('access_token', $token));
    }

    public function logout(): JsonResponse
    {
        return response()->json(['data' => 'success'])->withoutCookie('access_token');
    }

    /**
     * @throws AuthorizationException
     */
    public function impersonate(Request $request)
    {
        $userIdToImpersonate = $request->input('user_id');
        $user = User::find($userIdToImpersonate);
        $tokenData = $this->impersonationService->impersonate($user);

        return redirect(Config::get('app.client_url').'/impersonate/?'.$tokenData['access_token']);
    }
}
