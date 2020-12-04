<?php

namespace Tests\Utils\WebsiteExtraction;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebsiteExtractionHelperTest extends TestCase
{
    public function testMakeContentSearchable(): void
    {
        $testString = <<<EOD
<h1>Heading 1</h1>
<h2>Heading 2</h2>
<h3>Heading 3</h3>
<h4>Heading 4</h4>
<h5>Heading 5</h5>
<h6>Heading 6</h6>
<p>Paragraph Text</p><script>alert('xss');</script>
<pre>\$mycool++;</pre>
<p><img alt='friend' src='bad_image.jpg' /><a href='coolplace.html'>My cool link</a></p>
<p><strong>I'm super bold and cool</strong></p>

<ul><li>First Item</li><li>Second Item</li></ul>
<blockquote>Hello</blockquote>
EOD;
        $expected = <<<EOD
Paragraph Text

```
\$mycool++;
```

My cool link

**I'm super bold and cool**

- First Item
- Second Item

> Hello
EOD;

        $result = app(WebsiteExtractionHelper::class)->makeContentSearchable($testString);
        self::assertEquals($expected, $result);

        $result = app(WebsiteExtractionHelper::class)->makeContentSearchable('');
        self::assertEquals('', $result);
    }

    /**
     * @group n
     */
    public function testFullFetch()
    {
        $result = app(WebsiteExtractionFactory::class)->make('https://d9db56472fd41226d193-1e5e0d4b7948acaf6080b0dce0b35ed5.ssl.cf1.rackcdn.com/spectools/docs/wd-spectools-word-sample-04.doc')->getWebsite();
        dd($result);
//        $result = app(WebsiteExtractionHelper::class)->simpleFetch('https://www.khoslaventures.com/wp-content/uploads/Good-Group-Product-Manager.pdf');
        $result = app(WebsiteExtractionHelper::class)->downloadAndExtract('https://d9db56472fd41226d193-1e5e0d4b7948acaf6080b0dce0b35ed5.ssl.cf1.rackcdn.com/spectools/docs/wd-spectools-word-sample-04.doc');
        dd($result);
//        Queue::fake();
//        $this->refreshDb();
//        $card = app(CardRepository::class)->updateOrInsert([
//            'title'        => 'hello',
//            'url'          => 'https://www.khoslaventures.com/wp-content/uploads/Good-Group-Product-Manager.pdf',
//            'user_id'      => 1,
//            'card_type_id' => 1,
//        ]);
//        $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards('link');
//        $result = $syncCardsIntegration->saveCardData($card);
//        dd(Card::find($card->id));
    }
}
