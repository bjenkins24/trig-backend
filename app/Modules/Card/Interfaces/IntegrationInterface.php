<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;
use App\Models\User;

interface IntegrationInterface
{
    public function syncCards(User $user);

    public function saveCardData(Card $card): void;
}
