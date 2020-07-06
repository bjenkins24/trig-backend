<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCard
{
    /**
     * Belongs to Card.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo('App\Models\Card');
    }
}
