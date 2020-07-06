<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPermissions
{
    /**
     * Has many permissions.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany('App\Models\Permission');
    }
}
