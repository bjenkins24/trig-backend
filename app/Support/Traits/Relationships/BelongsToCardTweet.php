<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCardTweet
{
    /**
     * Belongs to CardTweet.
     */
    public function cardTweet(): BelongsTo
    {
        return $this->belongsTo('App\Models\CardTweet');
    }
}
