<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Traits\HandlesAuth;
use Illuminate\Http\Request;
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
    public function login(Request $request)
    {
        $user = null;
        $auth_token = $this->authRequest($request->all());

        if (! empty(Arr::get($auth_token, 'access_token'))) {
            $user = User::where('email', $request->get('email'))->first();
        }

        return compact('auth_token', 'user');
    }
}
