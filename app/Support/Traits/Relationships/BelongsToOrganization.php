<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    /**
     * Belongs to Organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo('App\Models\Organization');
    }
}
