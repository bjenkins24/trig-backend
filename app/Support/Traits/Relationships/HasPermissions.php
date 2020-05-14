<?php

namespace App\Support\Traits\Relationships;

trait HasPermissions
{
    /**
     * Has many permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        return $this->hasMany('App\Models\Permission');
    }
}
