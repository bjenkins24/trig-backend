<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\MorphOne;

trait PermissionTypeable
{
    /**
     * Get the owning permission model.
     */
    public function permissionType(): MorphOne
    {
        return $this->morphOne('App\Models\PermissionType', 'typeable');
    }
}
