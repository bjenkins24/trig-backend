<?php

namespace App\Support\Traits\Relationships;

trait HasTeams
{
    /**
     * Has many teams.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teams()
    {
        return $this->hasMany('App\Models\Team');
    }
}
