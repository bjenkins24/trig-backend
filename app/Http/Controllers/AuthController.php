<?php

namespace App\Http\Controllers;

use App\Exceptions\Auth\NoAccessTokenSet;
use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Modules\User\UserRepository;
use App\Support\Traits\HandlesAuth;

class AuthController extends Controller
{
    use HandlesAuth;

    public UserRepository $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * Log in user.
     *
     * @param null  $root
     * @param array $args
     *
     * @return User
     */
    public function login(Login $request)
    {
        try {
            $authToken = $this->authRequest($request->all());
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $message = 'invalid_grant' === $error ?
                'The email or password you entered was incorrect. Please try again.' :
                'Something went wrong. Please try again';

            return response()->json([
                'error'   => $error,
                'message' => $message,
            ]);
        }

        if (empty(\Arr::get($authToken, 'access_token'))) {
            throw new NoAccessTokenSet();
        }

        $user = $this->userRepo->findByEmail($request->get('email'));

        return response()->json(['data' => compact('authToken', 'user')], 200);
    }
}
