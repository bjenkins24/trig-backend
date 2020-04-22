<?php

namespace App\Support\Traits\Relationships;

trait BelongsToPermissionType
{
    /**
     * Belongs to PermissionType.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function permissionType()
    {
        return $this->belongsTo('App\Models\PermissionType');
    }
}
