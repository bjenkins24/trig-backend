<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Traits\HandlesAuth;
use Illuminate\Http\Request;

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

        return response()->json(['data' => $auth_token], 200);
    }
}
