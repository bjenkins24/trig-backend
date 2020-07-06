<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCards
{
    /**
     * Has many cards.
     */
    public function cards(): HasMany
    {
        return $this->hasMany('App\Models\Card');
    }
}
