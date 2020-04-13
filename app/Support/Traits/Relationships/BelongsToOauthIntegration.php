<?php

namespace App\Support\Traits\Relationships;

trait BelongsToOauthIntegration
{
    /**
     * Belongs to oauthIntegration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function oauthIntegration()
    {
        return $this->belongsTo('App\Models\OauthIntegration');
    }
}
