<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\User\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
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

        if (User::where('email', $request->get('email'))) {
            return response()->json([
               'error' => 'user_exists', 'message' => 'The email you tried to register already exists',
            ], 200);
        }

        return $this->user->createAccount($request->all());
    }
}
