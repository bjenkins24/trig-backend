<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Support\Traits\HandlesAuth;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    use HandlesAuth;

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

        $user = null;
        if (! empty(Arr::get($authToken, 'access_token'))) {
            $user = User::where('email', $request->get('email'))->first()->toArray();
        }

        return response()->json(['data' => compact('authToken', 'user')], 200);
    }
}
