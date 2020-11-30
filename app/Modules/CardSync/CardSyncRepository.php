<?php

namespace App\Modules\CardSync;

use App\Models\CardSync;
use Illuminate\Support\Carbon;

class CardSyncRepository
{
    public function create(array $values): CardSync
    {
        return CardSync::create($values);
    }

    public function getLastAttempt(int $cardId): ?CardSync
    {
        return CardSync::where('card_id', $cardId)->orderBy('created_at', 'desc')->first();
    }

    public function secondsSinceLastAttempt(int $cardId): ?int
    {
        $lastSyncAttempt = $this->getLastAttempt($cardId);
        if ($lastSyncAttempt) {
            return $lastSyncAttempt->created_at->diffInSeconds(Carbon::now());
        }

        return null;
    }
}
