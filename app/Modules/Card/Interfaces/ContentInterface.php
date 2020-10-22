<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;

interface ContentInterface
{
    public function getCardContent(Card $card, int $id, string $mimeType);
}
