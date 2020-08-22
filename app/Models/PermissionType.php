<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToPermission;

/**
 * App\Models\PermissionType.
 *
 * @property int                                           $id
 * @property string|null                                   $typeable_type
 * @property int|null                                      $typeable_id
 * @property int                                           $permission_id
 * @property \Illuminate\Support\Carbon|null               $created_at
 * @property \Illuminate\Support\Carbon|null               $updated_at
 * @property \App\Models\Permission                        $permission
 * @property \Illuminate\Database\Eloquent\Model|\Eloquent $permissionTypeable
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\PermissionType newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\PermissionType newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\PermissionType query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType whereTypeableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType whereTypeableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PermissionType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class PermissionType extends BaseModel
{
    use BelongsToPermission;

    /**
     * Get the owning imageable model.
     */
    public function permissionTypeable()
    {
        return $this->morphTo();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'typeable_type',
        'typeable_id',
        'permission_id',
    ];
}
