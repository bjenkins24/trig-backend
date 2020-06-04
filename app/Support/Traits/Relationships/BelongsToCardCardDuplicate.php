<?php

namespace App\Support\Traits\Relationships;

use App\Models\Card;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCardCardDuplicate
{
    public function primaryCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'id', 'primary_card_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'id', 'duplicate_card_id');
    }
}
