<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardFavorite;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCardFavorites
{
    /**
     * Has one card favorite.
     */
    public function cardFavorites(): HasMany
    {
        return $this->hasMany(CardFavorite::class);
    }
}
