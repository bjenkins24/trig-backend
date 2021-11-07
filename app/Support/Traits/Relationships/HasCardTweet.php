<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardTweet;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardTweet
{
    /**
     * Has one tweet.
     */
    public function cardTweet(): HasOne
    {
        return $this->hasOne(CardTweet::class);
    }
}
