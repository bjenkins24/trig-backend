<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCapability
{
    /**
     * Belongs to Capability.
     */
    public function capability(): BelongsTo
    {
        return $this->belongsTo('App\Models\Capability');
    }
}
