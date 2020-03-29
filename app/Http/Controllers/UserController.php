<?php

namespace App\Http\Controllers;

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
        $this->user->createAccount($request->all());
    }
}
