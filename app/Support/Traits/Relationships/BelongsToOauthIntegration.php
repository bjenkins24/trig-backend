<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOauthIntegration
{
    /**
     * Belongs to oauthIntegration.
     */
    public function oauthIntegration(): BelongsTo
    {
        return $this->belongsTo('App\Models\OauthIntegration');
    }
}
