<?php

namespace App\Support\Traits\Relationships;

trait Permissionables
{
    /**
     * Get the owning imageable model.
     */
    public function permissions()
    {
        return $this->morphMany('App\Models\Permission', 'permissionable');
    }
}
