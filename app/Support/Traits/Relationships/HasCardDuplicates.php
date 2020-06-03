<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardDuplicate;

trait HasCardDuplicates
{
    public function cardDuplicates()
    {
        return $this->hasMany(CardDuplicate::class, 'primary_card_id', 'id');
    }

    public function primaryDuplicate()
    {
        return $this->hasOne(CardDuplicate::class, 'duplicate_card_id', 'id');
    }
}
