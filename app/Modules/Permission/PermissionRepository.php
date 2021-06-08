<?php

namespace App\Modules\Permission;

use App\Models\Permission;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\Person\PersonRepository;
use App\Modules\User\UserRepository;

class PermissionRepository
{
    /**
     * Create a permission.
     *
     * @param [mixed] $permissionType Card or Collection
     */
    private function create($permissionType, string $capability): Permission
    {
        return $permissionType->permissions()->create([
            'capability_id' => app(CapabilityRepository::class)->get($capability)->id,
        ]);
    }

    /**
     * Create a permission using an email. This will either make a user (if it exists) permission, or
     * a person commission if it doesn't.
     *
     * @param [mixed] $permissionType
     *
     * @return mixed
     */
    public function createEmail($permissionType, string $capability, string $email): Permission
    {
        $userRepo = app(UserRepository::class);
        $permission = $this->create($permissionType, $capability);
        $user = $userRepo->findByEmail($email);
        if ($user) {
            $userRepo->createPermission($user, $permission);

            return $permission;
        }

        $personRepo = app(PersonRepository::class);
        $person = $personRepo->firstOrCreate($email);
        $personRepo->createPermission($person, $permission);

        return $permission;
    }

    /**
     * Anyone can discover/read a file. Blank permission_typeable fields allow for this.
     *
     * @param [mixed] $permissionType
     */
    public function createAnyone($permissionType, string $capability): Permission
    {
        $permission = $this->create($permissionType, $capability);
        $permission->permissionType()->create();

        return $permission;
    }
}
