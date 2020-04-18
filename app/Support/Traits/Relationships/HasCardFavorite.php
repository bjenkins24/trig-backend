<?php

namespace App\Support\Traits\Relationships;

trait HasCardFavorite
{
    /**
     * Has one card favorite.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cardFavorite()
    {
        return $this->hasOne('App\Models\CardFavorite');
    }
}
