<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;

class AuthController extends Controller
{
    use HandlesAuth;

    public UserService $user;

    public function __construct(UserService $user)
    {
        $this->user = $user;
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
                'error'   => $e->getMessage(),
                'message' => $message,
            ]);
        }

        if (empty(\Arr::get($authToken, 'access_token'))) {
            throw new \Error('A user tried to log in, they were authenticated, but the access token was not set');
        }

        $user = $this->user->repo->findByEmail($request->get('email'));

        return response()->json(['data' => compact('authToken', 'user')], 200);
    }
}
