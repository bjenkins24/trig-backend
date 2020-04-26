<?php

namespace App\Modules\Permission;

use App\Models\Permission;
use App\Models\Person;
use App\Models\Team;
use App\Models\Team\TeamRepository;
use App\Models\User;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\User\UserRepository;

class PermissionRepository
{
    private CapabilityRepository $capability;
    private UserRepository $user;
    private PersonRepository $person;
    private LinkShareSettingRepository $linkShareSetting;
    private TeamRepository $team;

    public function __construct(
        UserRepository $user,
        CapabilityRepository $capability,
        PersonRepository $person,
        LinkShareSettingRepository $linkShareSetting,
        TeamRepository $team
    ) {
        $this->capability = $capability;
        $this->user = $user;
        $this->person = $person;
        $this->linkShareSetting = $linkShareSetting;
        $this->team = $team;
    }

    /**
     * Create a permission.
     *
     * @param [mixed] $permissionType Card or Deck
     *
     * @return void
     */
    private function create($permissionType, string $capability): Permission
    {
        return $permissionType->permissions()->create([
            'capability_id' => $this->capability->get($capability)->id,
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
        $permission = $this->create($permissionType, $capability);
        $user = $this->user->findByEmail($email);
        if ($user) {
            return $this->user->createPermission($user, $permission);
        }
        $person = $this->person->firstOrCreate($email);
        $this->person->createPermission($person, $permission);

        return $permission;
    }

    /**
     * Anyone can discover/read a file. Blank permission_typeable fields allow for this.
     *
     * @param [mixed] $permissionType
     *
     * @return void
     */
    public function createAnyone($permissionType, string $capability): Permission
    {
        $permission = $this->create($permissionType, $capability);
        $permisson->permissionType()->create();

        return $permission;
    }

    public function createTeam($permissionType, string $capability, Team $team): Permission
    {
        $permission = $this->create($permissionType, $capability);
        $this->team->createPermission($team, $permission);

        return $permission;
    }
}
