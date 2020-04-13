<?php

namespace App\Support\Traits\Relationships;

trait BelongsToUser
{
    /**
     * Belongs to user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
