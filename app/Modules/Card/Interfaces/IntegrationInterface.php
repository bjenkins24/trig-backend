<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;

interface IntegrationInterface
{
    public function syncCards(int $userId, ?int $since = null);

    public function saveCardData(Card $card): void;
}
