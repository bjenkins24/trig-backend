<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOauthConnections
{
    /**
     * Has many oauthConnections.
     */
    public function oauthConnections(): HasMany
    {
        return $this->hasMany('App\Models\OauthConnection');
    }
}
