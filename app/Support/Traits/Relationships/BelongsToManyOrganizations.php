<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyOrganizations
{
    /**
     * Many to many relationship with organizations.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany('App\Models\Organization');
    }
}
