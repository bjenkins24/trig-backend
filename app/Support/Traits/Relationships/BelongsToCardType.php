<?php

namespace App\Support\Traits\Relationships;

trait BelongsToCardType
{
    /**
     * Belongs to CardType.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cardType()
    {
        return $this->belongsTo('App\Models\CardType');
    }
}
