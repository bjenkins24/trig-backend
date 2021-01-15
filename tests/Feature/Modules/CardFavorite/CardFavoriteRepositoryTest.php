<?php

namespace Tests\Feature\Modules\CardFavorite;

use App\Models\Card;
use App\Models\CardFavorite;
use App\Modules\CardFavorite\CardFavoriteRepository;
use Tests\TestCase;

class CardFavoriteRepositoryTest extends TestCase
{
    public function testGetUserIdsByCard(): void
    {
        $this->refreshDb();
        $card = Card::find(1);
        $userId1 = 1;
        $userId2 = 2;

        $results = app(CardFavoriteRepository::class)->getUserIdsByCard($card);
        self::assertEquals(collect([]), $results);

        CardFavorite::create([
            'card_id'    => $card->id,
            'user_id'    => $userId1,
        ]);
        CardFavorite::create([
            'card_id'    => $card->id,
            'user_id'    => $userId2,
        ]);

        $results = app(CardFavoriteRepository::class)->getUserIdsByCard($card);
        self::assertEquals(collect([$userId1, $userId2]), $results);
    }
}
