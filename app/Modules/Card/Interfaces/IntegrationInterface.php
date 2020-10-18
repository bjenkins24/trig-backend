<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Collection;

interface IntegrationInterface
{
    public static function getIntegrationKey(): string;

    public function getAllCardData(User $user, ?int $since): Collection;

    public function getCardContent(Card $card, int $id, string $mimeType);
}
