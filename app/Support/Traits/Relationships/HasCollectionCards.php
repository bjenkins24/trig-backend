<?php

namespace App\Support\Traits\Relationships;

use App\Models\CollectionCard;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCollectionCards
{
    /**
     * Has many cards.
     */
    public function collectionCards(): HasMany
    {
        return $this->hasMany(CollectionCard::class);
    }
}
