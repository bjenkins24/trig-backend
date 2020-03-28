<?php

namespace App\Modules\User;

use App\Models\User;
use App\Modules\User\Repositories\CreateAccount;
use App\Modules\User\Repositories\UpdateAccount;

class UserService
{
    /**
     * Create account repository.
     *
     * @var CreateAccount
     */
    protected $create;

    /**
     * Update account repository.
     *
     * @var UpdateAccount
     */
    protected $update;

    /**
     * Create instance of user service.
     */
    public function __construct(CreateAccount $create, UpdateAccount $update)
    {
        $this->create = $create;
        $this->update = $update;
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

    /**
     * Update user's account.
     *
     * @return User
     */
    public function updateAccount(User $user, array $input)
    {
        return $this->update->handle($user, $input);
    }
}
