<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;
use App\Models\User;

interface IntegrationInterface
{
    public static function getIntegrationKey(): string;

    public function getAllCardData(User $user, ?int $since): array;

    public function getCardContent(Card $card, int $id, string $mimeType);
}
