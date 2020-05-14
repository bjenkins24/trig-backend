<?php

namespace App\Support\Traits\Relationships;

trait PermissionTypeable
{
    /**
     * Get the owning permission model.
     */
    public function permissionType()
    {
        return $this->morphOne('App\Models\PermissionType', 'typeable');
    }
}
