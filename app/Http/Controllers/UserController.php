<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\User\UserService;
use App\Support\Traits\HandlesAuth;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesAuth;

    /**
     * @var UserService
     */
    private $user;

    public function __construct(UserService $user)
    {
        $this->user = $user;
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
    public function register(Request $request)
    {
        $rules = [
            'email'     => 'required',
            'password'  => 'required',
            'terms'     => 'required',
        ];
        $request->validate($rules);

        if (User::where('email', $request->get('email'))->exists()) {
            return response()->json([
               'error' => 'user_exists', 'message' => 'The email you tried to register already exists',
            ], 200);
        }

        $this->user->createAccount($request->all());

        // Login the new user
        $auth_token = $this->authRequest($request->all());

        return response()->json(['data' => $auth_token], 201);
    }
}
