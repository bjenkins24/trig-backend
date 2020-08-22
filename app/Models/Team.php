<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use App\Support\Traits\Relationships\BelongsToOrganization;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Team.
 *
 * @property int                                                         $id
 * @property int                                                         $organization_id
 * @property string                                                      $name
 * @property \Illuminate\Support\Carbon|null                             $created_at
 * @property \Illuminate\Support\Carbon|null                             $updated_at
 * @property \App\Models\Organization                                    $organization
 * @property \App\Models\PermissionType|null                             $permissionType
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property int|null                                                    $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
