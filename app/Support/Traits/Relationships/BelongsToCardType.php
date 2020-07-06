<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCardType
{
    /**
     * Belongs to CardType.
     */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo('App\Models\CardType');
    }
}
