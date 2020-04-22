<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCapability;
use App\Support\Traits\Relationships\HasPermissionType;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use BelongsToCapability;
    use HasPermissionType;

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
