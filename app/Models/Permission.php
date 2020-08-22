<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCapability;
use App\Support\Traits\Relationships\HasPermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Permission.
 *
 * @property int                                           $id
 * @property string                                        $permissionable_type
 * @property int                                           $permissionable_id
 * @property int                                           $capability_id
 * @property \Illuminate\Support\Carbon|null               $created_at
 * @property \Illuminate\Support\Carbon|null               $updated_at
 * @property \App\Models\Capability                        $capability
 * @property \App\Models\PermissionType|null               $permissionType
 * @property \Illuminate\Database\Eloquent\Model|\Eloquent $permissionable
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission whereCapabilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission wherePermissionableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission wherePermissionableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Permission whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Permission extends Model
{
    use BelongsToCapability;
    use HasPermissionType;

    /**
     * Update a card when a permission is updated to make sure
     * elastic search is up to date.
     *
     * @var array
     */
    protected $touches = ['permissionable'];

    /**
     * Get the owning permissionable model.
     */
    public function permissionable()
    {
        return $this->morphTo();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'permissionable_type',
        'permissionable_id',
        'capability_id',
    ];
}
