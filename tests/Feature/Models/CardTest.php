<?php

namespace Tests\Feature\Models;

use App\Models\Card;
use Tests\TestCase;

class CardTest extends TestCase
{
    public function testToSearchableArray()
    {
        $card = Card::find(1);
        $card->properties = ['title' => \Config::get('constants.seed.card.doc_title')];
        $card->save();
        $result = $card->toSearchableArray();
        // Remove stuff that's hard to test for
        unset($result['title']);
        unset($result['actual_created_at']);

        $this->assertEquals($result, [
            'user_id'                     => '1',
            'card_type_id'                => '3',
            'organization_id'             => 1,
            'doc_title'                   => \Config::get('constants.seed.card.doc_title'),
            'content'                     => \Config::get('constants.seed.card.content'),
            'permissions'                 => [],
        ]);
    }
}
