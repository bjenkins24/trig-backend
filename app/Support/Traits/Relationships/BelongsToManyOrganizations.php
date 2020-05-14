<?php

namespace App\Support\Traits\Relationships;

trait BelongsToManyOrganizations
{
    /**
     * Many to many relationship with organizations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function organizations()
    {
        return $this->belongsToMany('App\Models\Organization');
    }
}
