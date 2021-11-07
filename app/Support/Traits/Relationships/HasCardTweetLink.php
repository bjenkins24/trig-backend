<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardTweetLink;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardTweetLink
{
    /**
     * Has one cardTweetLink.
     */
    public function cardTweetLink(): HasOne
    {
        return $this->hasOne(CardTweetLink::class);
    }
}
