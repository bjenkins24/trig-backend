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
        $rules = [
            'email'     => 'required',
            'password'  => 'required',
        ];
        $request->validate($rules);

        try {
            $auth_token = $this->authRequest($request->all());
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
        if (! empty(Arr::get($auth_token, 'access_token'))) {
            $user = User::where('email', $request->get('email'))->first()->toArray();
        }

        return response()->json(['data' => compact('auth_token', 'user')], 200);
    }
}
