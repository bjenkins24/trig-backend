<?php

namespace App\Modules\User\Repositories;

use App\Events\User\AccountCreated;
use App\Models\User;

class CreateAccount
{
    /**
     * Create new user account.
     *
     * @param array $input
     *
     * @return User
     */
    public function handle(array $input)
    {
        $attrs = collect($input)->except([
            'password_confirmation', 'password',
        ])
        ->filter()
        ->merge(['password' => bcrypt($input['password'])])
        ->all();

        $user = User::create(array_merge($attrs, [
            'terms_of_service_accepted_at' => now(),
        ]));

        event(new AccountCreated($user));

        return $user;
    }
}
