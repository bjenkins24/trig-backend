<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardTweetReply;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardTweetReply
{
    /**
     * Has one cardTweetReply.
     */
    public function cardTweetReply(): HasOne
    {
        return $this->hasOne(CardTweetReply::class);
    }
}
