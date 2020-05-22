<?php

namespace App\Support\Traits\Relationships;

trait HasCardData
{
    /**
     * Has one CardData.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cardData()
    {
        return $this->hasOne('App\Models\CardData');
    }
}
