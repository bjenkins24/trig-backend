<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCapability;
use App\Support\Traits\Relationships\HasPermissionType;

class Permission extends BaseModel
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
