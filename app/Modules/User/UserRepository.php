<?php

namespace App\Modules\User;

use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getAllOauthConnections($user): Collection
    {
        return $user->oauthConnections()->get();
    }

    public function create(array $input): User
    {
        $attrs = collect($input)->except([
            'password', 'terms',
        ])
        ->filter()
        ->merge(['password' => bcrypt($input['password'])])
        ->all();

        $user = $this->user->create(array_merge($attrs, [
            'terms_of_service_accepted_at' => now(),
        ]));

        $user->organizations()->firstOrCreate([
            'name' => 'Squarespace',
        ]);

        return $user;
    }
}
