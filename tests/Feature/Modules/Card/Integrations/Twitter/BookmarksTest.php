<?php

namespace Tests\Feature\Modules\Card\Integrations\Twitter;

use App\Modules\Card\Integrations\Twitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarksTest extends TestCase
{
    use RefreshDatabase;

    private function getRawHtml(): string
    {
        return file_get_contents('tests/Feature/Modules/Card/Integrations/Twitter/rawHtml.html');
    }

    public function testGetTweets(): void
    {
        $tweets = app(Twitter\Bookmarks::class)->getTweets($this->getRawHtml());
        $this->assertEquals(8, $tweets->count());
        $this->assertEquals(1, $tweets->values()[0]->get('images')->count());

        $firstTweet = $tweets->values()[0];
        $this->assertArrayHasKey('@nickcald2021-10-13T16:34:58.000Z', $tweets->toArray());
        $this->assertEquals('Nick Caldwell', $firstTweet->get('name'));
        $this->assertEquals('@nickcald', $firstTweet->get('handle'));
        $this->assertEquals('2021-10-13T16:34:58.000Z', $firstTweet->get('created'));
        $this->assertEquals('https://pbs.twimg.com/profile_images/1435987121559330816/Nk1N53gB_x96.jpg', $firstTweet->get('avatar'));
        $this->assertEquals('While you were sleeping, just about completed my Jordan Poole prizm rainbow', $firstTweet->get('content'));
        $this->assertEquals('https://pbs.twimg.com/media/FBl9MEyUcAIx5Ep?format=jpg&name=medium', $firstTweet->get('images')[0]);

        $secondTweet = $tweets->values()[1];
        $link = $secondTweet->get('link');
        $this->assertEquals('https://t.co/SDnLUBqbrn?amp=1', $link->get('href'));
        $this->assertEquals('https://pbs.twimg.com/card_img/1456154525224652804/H5bX6Juw?format=jpg&name=medium', $link->get('image_src'));
        $this->assertEquals('producthunt.com', $link->get('url'));
        $this->assertEquals('Personal.ai - Remember everything with your own personal AI | Product Hunt', $link->get('title'));
        $this->assertEquals('We forget 80% of the information we experience every day. Speak, write or upload insights, information and experiences into your personal AI so you can recall your memories exactly when you need them.', $link->get('description'));

        $reply = $tweets->values()[4]->get('reply');
        $this->assertArrayHasKey('name', $reply);
        $this->assertArrayHasKey('handle', $reply);
        $this->assertArrayHasKey('created', $reply);
        $this->assertArrayHasKey('avatar', $reply);
        $this->assertArrayHasKey('replying_to', $reply);
        $this->assertArrayHasKey('content', $reply);
    }
}
