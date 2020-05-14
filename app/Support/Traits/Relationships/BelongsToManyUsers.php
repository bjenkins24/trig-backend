<?php

namespace App\Support\Traits\Relationships;

trait BelongsToManyUsers
{
    /**
     * Many to many relationship with users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany('App\Models\User');
    }
}
