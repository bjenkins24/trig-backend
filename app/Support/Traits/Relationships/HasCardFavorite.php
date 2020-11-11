<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardFavorite;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardFavorite
{
    /**
     * Has one card favorite.
     */
    public function cardFavorite(): HasOne
    {
        return $this->hasOne(CardFavorite::class);
    }
}
