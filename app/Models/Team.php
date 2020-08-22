<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use App\Support\Traits\Relationships\BelongsToOrganization;
use App\Support\Traits\Relationships\PermissionTypeable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * App\Models\Team.
 *
 * @property int                 $id
 * @property int                 $organization_id
 * @property string              $name
 * @property Carbon|null         $created_at
 * @property Carbon|null         $updated_at
 * @property Organization        $organization
 * @property PermissionType|null $permissionType
 * @property Collection|User[]   $users
 * @property int|null            $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\Team newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\Team newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\Team query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Team whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin Eloquent
 */
class Team extends BaseModel
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
