<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCardType
{
    /**
     * Belongs to CardType.
     */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }
}
