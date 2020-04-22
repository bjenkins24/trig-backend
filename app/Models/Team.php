<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use App\Support\Traits\Relationships\BelongsToOrganization;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use PermissionTypeable;
    use BelongsToOrganization;
    use BelongsToManyUsers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'organization_id',
        'name',
    ];
}
