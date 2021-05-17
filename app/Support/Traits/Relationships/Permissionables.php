<?php

namespace App\Support\Traits\Relationships;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Permissionables
{
    /**
     * Get the owning imageable model.
     */
    public function permissions(): MorphMany
    {
        return $this->morphMany(Permission::class, 'permissionable');
    }
}
