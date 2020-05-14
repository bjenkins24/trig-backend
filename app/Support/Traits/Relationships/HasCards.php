<?php

namespace App\Support\Traits\Relationships;

trait HasCards
{
    /**
     * Has many cards.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }
}
