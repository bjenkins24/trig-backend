<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardSync;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardSyncs
{
    public function cardSyncs(): HasMany
    {
        return $this->hasMany(CardSync::class, 'card_id', 'id');
    }

    // We're using this _only_ in searching all cards so we ony query for one card
    // This is a hack and not a good representation of the data
    public function cardSync(): HasOne
    {
        return $this->hasOne(CardSync::class, 'card_id', 'id');
    }
}
