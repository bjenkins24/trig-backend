<?php

namespace App\Modules\User;

use App\Models\Permission;
use App\Models\PermissionType;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getAllOauthConnections(User $user): Collection
    {
        return $user->oauthConnections()->get();
    }

    public function createPermission(User $user, Permission $permission): PermissionType
    {
        return $user->permissionType()->create([
            'permission_id' => $permission->id,
        ]);
    }

    /**
     * Given a domain name is it an active google drive domain integration? Individual
     * domains can be enabled and disabled from within Trig.
     */
    public function isGoogleDomainActive(User $user, string $domain): bool
    {
        return (bool) $user->properties->get('google_domains')->where($domain, true);
    }

    public function create(array $input): User
    {
        $attrs = collect($input)->except([
            'password', 'terms',
        ])
        ->filter()
        ->merge(['password' => bcrypt($input['password'])])
        ->all();

        User::create(array_merge($attrs, [
            'terms_of_service_accepted_at' => now(),
        ]));

        $user->organizations()->firstOrCreate([
            'name' => 'Squarespace',
        ]);

        return $user;
    }
}
