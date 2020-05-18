<?php

namespace App\Support\Traits\Relationships;

trait BelongsToManyTeams
{
    /**
     * Many to many relationship with teams.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teams()
    {
        return $this->belongsToMany('App\Models\Team');
    }
}
