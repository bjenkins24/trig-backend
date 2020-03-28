<?php

namespace App\Modules\User\Repositories;

use App\Models\User;

class UpdateAccount
{
    /**
     * Update a user's account.
     *
     * @param User  $user
     * @param array $input
     *
     * @return User
     */
    public function handle(User $user, array $input)
    {
        $attrs = collect($input)->except([
            'current_password',
            'new_password',
            'new_password_confirmation',
        ])->filter()->all();

        $user->fill($attrs);
        $currentPassword = array_get($input, 'current_password');
        $password = array_get($input, 'new_password');

        if (! empty($password)) {
            if (! \Hash::check($currentPassword, $user->password)) {
                throw new \GraphQL\Error\Error('Current password is invalid.');
            }

            $user->password = bcrypt($password);
        }

        $user->save();

        return $user;
    }
}
