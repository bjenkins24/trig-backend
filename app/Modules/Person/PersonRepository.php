<?php

namespace App\Modules\Person;

use App\Models\Permission;
use App\Models\PermissionType;
use App\Models\Person;

class PersonRepository
{
    public function firstOrCreate(string $email): Person
    {
        return Person::firstOrCreate(['email' => $email]);
    }

    public function createPermission(Person $person, Permission $permission): PermissionType
    {
        return $person->permissionType()->create([
            'permission_id' => $permission->id,
        ]);
    }
}
