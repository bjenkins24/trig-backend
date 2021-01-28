<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CardDedupe;
use App\Models\Card;
use App\Modules\Card\CardRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function testCardDedupe(): void
    {
        $this->partialMock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('dedupe')->once();
        });

        $syncCards = new CardDedupe(Card::find(1));
        $syncCards->handle();
    }
}
