<?php

namespace App\Modules\Team;

use App\Models\Team;

class TeamRepository
{
    private Team $team;

    public function __construct(Team $team)
    {
        $this->team = $team;
    }

    public function createPermission(Team $team, Permission $permission): PermissionType
    {
        return $this->team->permissionType()->create([
            'permission_id' => $permission->id,
        ]);
    }
}
