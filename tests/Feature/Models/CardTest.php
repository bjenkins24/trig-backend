<?php

namespace Tests\Feature\Models;

use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Undocumented function.
     *
     * @return void
     * @group n
     */
    public function testToSearchableArray()
    {
        $card = Card::find(1);
        $card->cardData()->create([
            'title'   => 'hello',
            'content' => 'no way',
            'created' => null,
        ]);
        $result = $card->toSearchableArray();
        dd($result);
    }
}
