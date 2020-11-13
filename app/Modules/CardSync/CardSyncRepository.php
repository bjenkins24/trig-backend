<?php

namespace App\Modules\CardSync;

use App\Models\CardSync;

class CardSyncRepository
{
    public function create(array $values): CardSync
    {
        return CardSync::create($values);
    }
}
