<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CardDedupe;
use App\Models\Card;
use App\Modules\Card\CardRepository;
use Tests\TestCase;

class CardDedupeTest extends TestCase
{
    public function testCardDedupe()
    {
        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('dedupe')->once();
        });

        $syncCards = new CardDedupe(Card::find(1));
        $syncCards->handle();
    }
}