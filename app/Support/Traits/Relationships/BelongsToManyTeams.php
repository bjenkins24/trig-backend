<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyTeams
{
    /**
     * Many to many relationship with teams.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany('App\Models\Team');
    }
}
