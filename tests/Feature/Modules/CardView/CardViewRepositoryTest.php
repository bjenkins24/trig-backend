<?php

namespace Tests\Feature\Modules\CardView;

use App\Models\Card;
use App\Models\CardView;
use App\Modules\CardView\CardViewRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CardViewRepositoryTest extends TestCase
{
    public function testDenormalizeCardViews(): void
    {
        $this->refreshDb();
        $card = Card::find(1);
        $createdAt = Carbon::parse('2010-01-01 00:00:00')->timestamp;
        $userId1 = 1;
        $userId2 = 2;

        $results = app(CardViewRepository::class)->denormalizeCardViews($card);
        self::assertEquals(collect([]), $results);

        CardView::insert([
            'card_id'    => $card->id,
            'user_id'    => $userId1,
            'created_at' => $createdAt,
        ]);
        CardView::insert([
            'card_id'    => $card->id,
            'user_id'    => $userId2,
            'created_at' => $createdAt,
        ]);

        $results = app(CardViewRepository::class)->denormalizeCardViews($card);

        self::assertEquals(collect([
            ['user_id' => $userId1, 'created_at' => $createdAt],
            ['user_id' => $userId2, 'created_at' => $createdAt],
        ]), $results);
    }
}
