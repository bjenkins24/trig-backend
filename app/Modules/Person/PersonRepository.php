<?php

namespace App\Modules\Permission;

use App\Models\Permission;
use App\Models\Person;
use App\Modules\Person\PersonRepository;

class PermissionRepository
{
    private PersonRepository $person;

    public function __construct(PersonRepository $person)
    {
        $this->person = $person;
    }

    public function findByEmail(string $email): Person
    {
        return Person::where(['email' => $email]);
    }

    public function firstOrCreate(string $email): Person
    {
        return Person::firstOrCreate(['email' => $email]);
    }

    public function createPermission(Person $person, Permission $permission): PermissionType
    {
        return $this->person->permissionType()->create([
            'permission_id' => $permission->id,
        ]);
    }
}
