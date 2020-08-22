<?php

namespace App\Support\Traits\Relationships;

use App\Models\OauthIntegration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOauthIntegration
{
    /**
     * Belongs to oauthIntegration.
     */
    public function oauthIntegration(): BelongsTo
    {
        return $this->belongsTo(OauthIntegration::class);
    }
}
