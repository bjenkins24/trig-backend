<?php

namespace Tests\Feature\Models;

use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CardTest extends TestCase
{
    public function testToSearchableArray(): void
    {
        $card = Card::find(1);
        $result = $card->toSearchableArray();

        self::assertEquals([
             'user_id'                          => '1',
             'url'                              => $card->url,
             'token'                            => $card->token,
             'title'                            => $card->title,
             'actual_created_at'                => $card->actual_created_at,
             'description'                      => $card->description,
             'created_at'                       => $card->created_at,
             'card_type'                        => 'Document',
             'favorites_by_user_id'             => [],
             'views'                            => [],
             'tags'                             => [],
             'workspace_id'                     => '2',
             'content'                          => Config::get('constants.seed.card.content'),
             'permissions'                      => [],
             'card_duplicate_ids'               => '1',
             'thumbnail'                        => 'https://coolstuff.com/public/'.ThumbnailHelper::IMAGE_FOLDER.'/thumbnail/'.$card->token.'.jpg',
             'thumbnail_width'                  => null,
             'thumbnail_height'                 => null,
        ], $result);
    }
}
