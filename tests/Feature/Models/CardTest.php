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
        $result = $card->toSearchableArray();
        // Remove stuff that's hard to test for
        unset($result['title'], $result['actual_created_at']);

        self::assertEquals($result, [
             'user_id'                     => '1',
             'url'                         => $card->url,
             'card_type'                   => 'Document',
             'favoritesByUserId'           => [],
             'views'                       => [],
             'organization_id'             => 1,
             'content'                     => Config::get('constants.seed.card.content'),
             'permissions'                 => [],
             'card_duplicate_ids'          => '1',
        ]);
    }
}
