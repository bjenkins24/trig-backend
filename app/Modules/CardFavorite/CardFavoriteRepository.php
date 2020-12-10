<?php

namespace App\Modules\CardFavorite;

use App\Models\CardFavorite;
use Illuminate\Support\Collection;

class CardFavoriteRepository
{
    public function getUserIdsByCard($card): Collection
    {
        $cards = CardFavorite::where('card_id', $card->id)->get();

        return collect($cards->map(static function ($item) {
            return (int) $item->user_id;
        }));
    }
}
