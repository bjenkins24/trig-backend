<?php

namespace App\Modules\CardView;

use App\Models\CardView;
use Illuminate\Support\Collection;

class CardViewRepository
{
    public function denormalizeCardViews($card): Collection
    {
        $cards = CardView::where('card_id', $card->id)->get();

        return collect($cards->map(static function ($item) {
            return [
                'user_id'    => (int) $item->user_id,
                'created_at' => $item->created_at->timestamp,
            ];
        }));
    }
}
