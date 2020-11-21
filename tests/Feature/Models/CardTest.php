<?php

namespace Tests\Feature\Models;

use App\Models\Card;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CardTest extends TestCase
{
    public function testToSearchableArray(): void
    {
        $card = Card::find(1);
        $card->properties = ['title' => Config::get('constants.seed.card.doc_title')];
        $card->save();
        $result = $card->toSearchableArray();
        // Remove stuff that's hard to test for
        unset($result['title'], $result['actual_created_at']);

        self::assertEquals($result, [
            'user_id'                     => '1',
            'card_type_id'                => '3',
            'organization_id'             => 1,
            'doc_title'                   => Config::get('constants.seed.card.doc_title'),
            'content'                     => Config::get('constants.seed.card.content'),
            'permissions'                 => [],
            'card_duplicate_ids'          => '1',
        ]);
    }
}
