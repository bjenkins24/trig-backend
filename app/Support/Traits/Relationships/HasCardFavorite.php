<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardFavorite
{
    /**
     * Has one card favorite.
     */
    public function cardFavorite(): HasOne
    {
        return $this->hasOne('App\Models\CardFavorite');
    }
}
