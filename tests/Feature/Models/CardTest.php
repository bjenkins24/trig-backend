<?php

namespace Tests\Feature\Models;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Throwable;

class CardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Exception|Throwable
     */
    public function testToSearchableArray(): void
    {
        $card = Card::find(1);
        $reply = [
            'name'        => 'brian',
            'handle'      => '@brian',
            'avatar'      => 'https:asdas',
            'replying_to' => 'asdasd',
            'content'     => 'asjdkasld',
        ];
        $link = [
            'href'            => 'brian',
            'image_src'       => '@brian',
            'url'             => 'https:asdas',
            'title'           => 'asdasd',
            'description'     => 'asjdkasld',
        ];
        $tweet = [
            'name'    => 'bro',
            'handle'  => 'sup',
            'avatar'  => 'no way',
            'image_1' => 'hello',
            'image_2' => 'goodbye',
            'image_3' => 'another',
            'image_4' => 'last one',
            'reply'   => $reply,
            'link'    => $link,
        ];
        app(CardRepository::class)->upsert([
            'collections' => [1],
            'tweet'       => $tweet,
        ], $card);
        $result = $card->toSearchableArray();

        self::assertEquals([
             'user_id'                                             => '1',
             'collections'                                         => [1],
             'url'                                                 => $card->url,
             'token'                                               => $card->token,
             'title'                                               => $card->title,
             'actual_created_at'                                   => $card->actual_created_at,
             'description'                                         => $card->description,
             'created_at'                                          => $card->created_at,
             'type_tag'                                            => 'Document',
             'type'                                                => 'application/pdf',
             'favorites_by_user_id'                                => [],
             'views'                                               => [],
             'tags'                                                => [],
             'workspace_id'                                        => '2',
             'content'                                             => Config::get('constants.seed.card.content'),
             'permissions'                                         => [],
             'card_duplicate_ids'                                  => '1',
             'screenshot_thumbnail'                                => 'https://coolstuff.com/public/'.ThumbnailHelper::IMAGE_FOLDER.'/screenshot_thumbnails/'.$card->token.'.jpg',
             'screenshot_thumbnail_width'                          => null,
             'screenshot_thumbnail_height'                         => null,
             'screenshot_thumbnail_large'                          => null,
             'screenshot_thumbnail_large_width'                    => null,
             'screenshot_thumbnail_large_height'                   => null,
             'thumbnail'                                           => 'https://coolstuff.com/public/'.ThumbnailHelper::IMAGE_FOLDER.'/image_thumbnails/'.$card->token.'.jpg',
             'thumbnail_width'                                     => null,
             'thumbnail_height'                                    => null,
             'twitter_name'                                        => $tweet['name'],
             'twitter_handle'                                      => $tweet['handle'],
             'twitter_avatar'                                      => $tweet['avatar'],
             'twitter_image_1'                                     => $tweet['image_1'],
             'twitter_image_2'                                     => $tweet['image_2'],
             'twitter_image_3'                                     => $tweet['image_3'],
             'twitter_image_4'                                     => $tweet['image_4'],
             'twitter_reply_name'                                  => $tweet['reply']['name'],
             'twitter_reply_handle'                                => $tweet['reply']['handle'],
             'twitter_reply_avatar'                                => $tweet['reply']['avatar'],
             'twitter_reply_replying_to'                           => $tweet['reply']['replying_to'],
             'twitter_reply_content'                               => $tweet['reply']['content'],
             'twitter_link_href'                                   => $tweet['link']['href'],
             'twitter_link_image_src'                              => $tweet['link']['image_src'],
             'twitter_link_url'                                    => $tweet['link']['url'],
             'twitter_link_title'                                  => $tweet['link']['title'],
             'twitter_link_description'                            => $tweet['link']['description'],
        ], $result);
    }
}
