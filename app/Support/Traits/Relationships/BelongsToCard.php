<?php

namespace App\Support\Traits\Relationships;

use App\Models\Card;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCard
{
    /**
     * Belongs to Card.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
