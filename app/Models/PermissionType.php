<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToPermission;
use Illuminate\Database\Eloquent\Model;

class PermissionType extends Model
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
        'permission_typeable_id',
        'permission_typeable_type',
        'permission_id',
    ];
}
