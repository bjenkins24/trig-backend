<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardDuplicate;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardDuplicates
{
    public function cardDuplicates(): HasMany
    {
        return $this->hasMany(CardDuplicate::class, 'primary_card_id', 'id');
    }

    public function primaryDuplicate(): HasOne
    {
        return $this->hasOne(CardDuplicate::class, 'duplicate_card_id', 'id');
    }
}
