<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasPermissionType
{
    /**
     * Has one PermissionType.
     */
    public function permissionType(): HasOne
    {
        return $this->hasOne('App\Models\PermissionType');
    }
}
