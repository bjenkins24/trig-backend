<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardTag;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCardTags
{
    /**
     * Has many cards.
     */
    public function cardTags(): HasMany
    {
        return $this->hasMany(CardTag::class);
    }
}
