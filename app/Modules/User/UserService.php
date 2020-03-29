<?php

namespace App\Modules\User;

use App\Models\User;
use App\Modules\User\Repositories\CreateAccount;

class UserService
{
    /**
     * Create account repository.
     *
     * @var CreateAccount
     */
    protected $create;

    /**
     * Create instance of user service.
     */
    public function __construct(CreateAccount $create)
    {
        $this->create = $create;
    }

    /**
     * Create a new user account.
     *
     * @return User
     */
    public function createAccount(array $input)
    {
        return $this->create->handle($input);
    }
}
