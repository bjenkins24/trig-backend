<?php

namespace App\Support\Traits\Relationships;

trait BelongsToCard
{
    /**
     * Belongs to Card.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function card()
    {
        return $this->belongsTo('App\Models\Card');
    }
}
