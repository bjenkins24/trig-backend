<?php

namespace Tests\Feature\Modules\Card\Integrations\Twitter;

use App\Models\User;
use App\Modules\Card\Integrations\Twitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarksTest extends TestCase
{
    use RefreshDatabase;

    private function getArrayOfRawHtml(): array
    {
        return [
            file_get_contents('tests/Feature/Modules/Card/Integrations/Twitter/rawHtml.html'),
        ];
    }

    /**
     * @group n
     */
    public function testGetTweets(): void
    {
        $rawHtml = $this->getArrayOfRawHtml();
        $tweets = app(Twitter\Bookmarks::class)->getTweets($rawHtml[0]);
        $this->assertEquals(10, $tweets->count());
        $this->assertEquals(1, $tweets->values()[0]->get('images')->count());
        dd($tweets);

        $firstTweet = $tweets->values()[0];
        $this->assertArrayHasKey('@nickcald2021-10-13T16:34:58.000Z', $tweets->toArray());
        $this->assertEquals('Nick Caldwell', $firstTweet->get('name'));
        $this->assertEquals('@nickcald', $firstTweet->get('handle'));
        $this->assertEquals('2021-10-13T16:34:58.000Z', $firstTweet->get('created_at'));
        $this->assertEquals('https://pbs.twimg.com/profile_images/1435987121559330816/Nk1N53gB_x96.jpg', $firstTweet->get('avatar'));
        $this->assertEquals('<span>While you were sleeping, just about completed my Jordan Poole prizm rainbow</span>', $firstTweet->get('content'));
        $this->assertEquals('https://pbs.twimg.com/media/FBl9MEyUcAIx5Ep?format=jpg&name=medium', $firstTweet->get('images')[0]);

        $secondTweet = $tweets->values()[1];
        $link = $secondTweet->get('link');
        $this->assertEquals('https://t.co/SDnLUBqbrn?amp=1', $link->get('href'));
        $this->assertEquals('https://pbs.twimg.com/card_img/1456154525224652804/H5bX6Juw?format=jpg&name=medium', $link->get('image_src'));
        $this->assertEquals('producthunt.com', $link->get('url'));
        $this->assertEquals('Personal.ai - Remember everything with your own personal AI | Product Hunt', $link->get('title'));
        $this->assertEquals('We forget 80% of the information we experience every day. Speak, write or upload insights, information and experiences into your personal AI so you can recall your memories exactly when you need them.', $link->get('description'));

        $fifthTweet = $tweets->values()[4];
        $reply = $fifthTweet->get('reply');
        $this->assertArrayHasKey('name', $reply);
        $this->assertArrayHasKey('handle', $reply);
        $this->assertArrayHasKey('created_at', $reply);
        $this->assertArrayHasKey('avatar', $reply);
        $this->assertArrayHasKey('replying_to', $reply);
        $this->assertArrayHasKey('content', $reply);
        // Make sure <br />'s are being added correctly
        $this->assertEquals('<span>I need to improve the positioning of </span><div><span><span class="card__link">@MDX_one</span></span></div><span> better.<br /><br />How does "A notion-based alternative to Ghost" sound?<br /><br />It\'s not just another Notion website builder.<br /><br /></span><div><span><span class="card__link">@MDX_one</span></span></div><span> is a blogging platform where creators can truly own their content and publish content effortlessly.</span>', $fifthTweet->get('content'));

        $contentWithHtml = $tweets->values()[4]->get('reply')->get('content');
        $this->assertEquals('<div><span>i never thought of it as a blogging engine </span><img alt="Exploding head" src="https://abs-0.twimg.com/emoji/v2/svg/1f92f.svg" title="Exploding head"/><span> will start recommending it as well now </span><img alt="Fire" src="https://abs-0.twimg.com/emoji/v2/svg/1f525.svg" title="Fire"/><img alt="Fire" src="https://abs-0.twimg.com/emoji/v2/svg/1f525.svg" title="Fire"/></div>', $contentWithHtml);

        $replyWithImage = $tweets->values()[8]->get('reply');
        $this->assertEquals('landon@inside.com', $replyWithImage->get('name'));
        $this->assertEquals('<div><span>Found this last year. Great advice.</span></div>', $replyWithImage->get('content'));
        $this->assertEquals('https://pbs.twimg.com/media/FCJdKHUXoAIFXmi?format=jpg&name=medium', $replyWithImage->get('image'));
    }

    public function testSaveTweets(): void
    {
        $arrayOfHtml = $this->getArrayOfRawHtml();
        $result = app(Twitter\Bookmarks::class)->getTweetsFromArrayOfHtml($arrayOfHtml);
        $this->assertEquals(8, app(Twitter\Bookmarks::class)->getTweets($arrayOfHtml[0])->count());
        $this->assertEquals(8, app(Twitter\Bookmarks::class)->getTweets($arrayOfHtml[1])->count());
        $this->assertEquals(9, $result->count());
        app(Twitter\Bookmarks::class)->saveTweetsFromArrayOfHtml($arrayOfHtml, User::find(1));
    }
}
