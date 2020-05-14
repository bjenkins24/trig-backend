<?php

namespace App\Support\Traits\Relationships;

trait HasOauthConnections
{
    /**
     * Has many oauthConnections.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function oauthConnections()
    {
        return $this->hasMany('App\Models\OauthConnection');
    }
}
