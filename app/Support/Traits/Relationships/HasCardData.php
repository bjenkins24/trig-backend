<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardData
{
    /**
     * Has one CardData.
     */
    public function cardData(): HasOne
    {
        return $this->hasOne('App\Models\CardData');
    }
}
