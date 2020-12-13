<?php

namespace App\Support\Traits\Relationships;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyOrganizations
{
    /**
     * Many to many relationship with organizations.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }
}
