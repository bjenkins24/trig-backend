<?php

namespace App\Support\Traits\Relationships;

use App\Models\Card;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCards
{
    /**
     * Has many cards.
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
