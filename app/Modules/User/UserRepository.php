<?php

namespace App\Modules\User;

use App\Models\Card;
use App\Models\OauthConnection;
use App\Models\Permission;
use App\Models\PermissionType;
use App\Models\User;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

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

    public function getOauthConnection(User $user, string $key): OauthConnection
    {
        $oauthIntegrationId = app(OauthIntegrationRepository::class)->findByName($key)->id;

        return $user->oauthConnections()->where(['oauth_integration_id' => $oauthIntegrationId])->first();
    }

    public function createPermission(User $user, Permission $permission): PermissionType
    {
        return $user->permissionType()->create([
            'permission_id' => $permission->id,
        ]);
    }

    public function getAllWorkspaces(User $user): Collection
    {
        return $user->workspaces()->get();
    }

    public function getTotalCards(User $user): int
    {
        $workspaceId = $user->workspaces()->first()->id;

        return Card::where('user_id', $user->id)->where('workspace_id', $workspaceId)->count();
    }

    /**
     * Given a domain name is it an active google drive domain integration? Individual
     * domains can be enabled and disabled from within Trig.
     */
    public function isGoogleDomainActive(User $user, string $domain): bool
    {
        foreach ($user->properties->get('google_domains') as $domainProperties) {
            foreach ($domainProperties as $allowedDomain => $isActive) {
                if ($allowedDomain === $domain && $isActive) {
                    return true;
                }
            }
        }

        return false;
    }

    public function update(User $user, array $input): User
    {
        // If all of these are true we can change the new password
        if (! empty($input['old_password']) && ! empty($input['new_password']) && Hash::check($input['old_password'], $user->password)) {
            $user->password = bcrypt($input['new_password']);
        }

        $fields = collect($input);
        $fields->each(static function ($fieldValue, $fieldKey) use (&$user) {
            if ('old_password' !== $fieldKey && 'new_password' !== $fieldKey) {
                $user->{$fieldKey} = $fieldValue;
            }
        });

        $user->save();
    }

    public function create(array $input): User
    {
        $attrs = collect($input)->except([
            'password', 'terms',
        ])
        ->filter()
        ->merge(['password' => bcrypt($input['password'])])
        ->all();

        $user = User::create(array_merge($attrs, [
            'terms_of_service_accepted_at' => now(),
        ]));

        $user->workspaces()->firstOrCreate([
            'name' => 'default',
        ]);

        return $user;
    }
}
