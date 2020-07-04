<?php

namespace App\Modules\CardType;

use App\Models\CardType;

class CardTypeRepository
{
    /**
     * Create a card type or return it.
     */
    public function firstOrCreate(string $name): CardType
    {
        return CardType::firstOrCreate(['name' => $name]);
    }
}
