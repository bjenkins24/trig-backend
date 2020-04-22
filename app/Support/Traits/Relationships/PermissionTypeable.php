<?php

namespace App\Support\Traits\Relationships;

trait PermissionTypeable
{
    /**
     * Get the owning permission model.
     */
    public function permissionTypeable()
    {
        return $this->morphOne('App\Models\PermissionType', 'permission_typeable');
    }
}
