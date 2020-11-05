<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Card;

interface ContentInterface
{
    public function getCardContentData(Card $card, ?string $id, string $mimeType);
}
