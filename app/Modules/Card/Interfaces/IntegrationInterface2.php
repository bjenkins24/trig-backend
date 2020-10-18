<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;
use App\Models\Card;
use Illuminate\Support\Collection;

interface IntegrationInterface2
{
    public function getAllCardData(User $user, ?int $since): Collection;

    public function getCardContent(Card $card, int $id, string $mimeType);
}
