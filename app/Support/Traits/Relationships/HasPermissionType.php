<?php

namespace App\Support\Traits\Relationships;

trait HasPermissionType
{
    /**
     * Has one PermissionType.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function permissionType()
    {
        return $this->hasOne('App\Models\PermissionType');
    }
}
