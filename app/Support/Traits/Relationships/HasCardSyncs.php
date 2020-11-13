<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardSync;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCardSyncs
{
    public function cardSyncs(): HasMany
    {
        return $this->hasMany(CardSync::class, 'card_id', 'id');
    }
}
