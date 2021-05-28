<?php

namespace Tests\Feature\Controllers;

use andreskrey\Readability\ParseException;
use App\Jobs\GetTags;
use App\Jobs\SaveCardData;
use App\Jobs\SaveCardDataInitial;
use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\CardSync\CardSyncRepository;
use App\Utils\ExtractDataHelper;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use App\Utils\WebsiteExtraction\WebsiteFactory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Storage;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCollection(): void
    {
        $this->client('POST', 'collection', [
            'title'       => '123',
            'description' => '123',
            'slug'        => '123',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testCreateCard(): void
    {
        Queue::fake();

        Carbon::setTestNow('2020-11-20 00:00:20');
        $this->mock(ExtractDataHelper::class);
        $now = Carbon::now();

        $data = [
            'url'                => 'https://google.com',
            'title'              => 'Google',
            'content'            => 'content',
            'description'        => 'Description',
            'createdAt'          => $now,
            'updatedAt'          => $now,
        ];

        $this->createCollection();

        $response = $this->client('POST', 'card', array_merge($data, ['collections' => [1, 2]]));
        // Check if the response returns an id
        $id = $this->getResponseData($response)->get('id');
        self::assertNotEmpty($id);

        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_updated_at'] = $now->toDateTimeString();
        $cardTypeId = CardType::where('name', '=', 'link')->first()->id;
        $data['card_type_id'] = $cardTypeId;
        $data['properties'] = null;
        unset($data['createdAt'], $data['updatedAt']);

        $this->assertDatabaseHas('cards', $data);
        $this->assertDatabaseHas('collection_cards', [
            'collection_id' => 1,
            'card_id'       => $id,
        ]);
        $this->assertDatabaseHas('collection_cards', [
            'collection_id' => 2,
            'card_id'       => $id,
        ]);
        Queue::assertPushed(SaveCardDataInitial::class, 1);
    }

    /**
     * @throws JsonException
     */
    public function testCreateCardNoProtocol(): void
    {
        Queue::fake();
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('POST', 'card', ['url' => 'google.com']);
        self::assertEquals('http://google.com', $this->getResponseData($response)->get('url'));
        $this->assertDatabaseHas('cards', [
            'url' => 'http://google.com',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testCreateCardFail(): void
    {
        $this->mock(ExtractDataHelper::class);
        $this->mock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('upsert')->andReturn(null);
        });

        $response = $this->client('POST', 'card', ['url' => 'http://google.com']);

        self::assertEquals('unexpected', $this->getResponseData($response, 'error')->get('error'));
    }

    /**
     * @throws JsonException
     */
    public function testCreateCardNoWorkspace(): void
    {
        $this->mock(ExtractDataHelper::class);
        $userId = 1;
        User::find($userId)->workspaces()->create([
            'name' => 'second 1',
        ]);
        $response = $this->client('POST', 'card', ['url' => 'http://google.com', 'user_id' => $userId]);

        self::assertEquals('bad_request', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(422, $response->getStatusCode());
    }

    public function testCreateCardDifferentCardType(): void
    {
        $this->mock(ExtractDataHelper::class);
        $data = [
            'url'       => 'http://google.com',
            'card_type' => 'twitter',
        ];
        $this->client('POST', 'card', $data);
        $cardTypeId = CardType::where('name', '=', 'twitter')->first()->id;

        $this->assertDatabaseHas('cards', [
            'url'          => $data['url'],
            'title'        => $data['url'],
            'card_type_id' => $cardTypeId,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testGetCardSuccess(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $response = $this->client('GET', "card/$cardId");
        self::assertEquals($cardId, $this->getResponseData($response)->get('id'));
    }

    /**
     * @throws JsonException
     */
    public function testGetAll(): void
    {
        $this->mock(ExtractDataHelper::class);
        $this->mock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('searchCards')->andReturn(collect([
                'cards'   => 'cards',
                'meta'    => 'meta',
                'filters' => 'filters',
            ]));
        });
        $response = $this->client('GET', 'cards');
        self::assertEquals('cards', $this->getResponseData($response, 'data')->get(0));
        self::assertEquals('meta', $this->getResponseData($response, 'meta')->get(0));
        self::assertEquals('filters', $this->getResponseData($response, 'filters')->get(0));
    }

    /**
     * @throws JsonException
     */
    public function testGetCardNotFound(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('GET', 'card/100');
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
    }

    /**
     * @throws JsonException
     */
    public function testGetCardForbidden(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('GET', 'card/5');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardSaveData(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();

        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $newCard = $this->getResponseData($response);

        // This is what would happen if we weren't faking the queue
        app(CardSyncRepository::class)->create([
            'card_id' => $newCard->get('id'),
            'status'  => 1,
        ]);

        $now = Carbon::now();

        $newData = [
            'url'                => 'https://newurl.com',
            'title'              => 'Cool new url',
            'description'        => 'cool new Description',
            'content'            => 'cool new content',
            'createdAt'          => $now,
            'updatedAt'          => $now,
            'isFavorited'        => true,
        ];

        $this->client('PATCH', 'card/'.$newCard->get('id'), $newData);

        Queue::assertPushed(SaveCardDataInitial::class, 1);
        // It shouldn't sync because it already did when we created it above
        Queue::assertPushed(SaveCardData::class, 0);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardSuccess(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();

        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $newCard = $this->getResponseData($response);

        $this->assertDatabaseMissing('card_favorites', [
            'card_id' => $newCard->get('id'),
            'user_id' => $newCard->get('user_id'),
        ]);

        // Since we're faking SaveCardData it's not going to say it saved. so let's save it manually here for the test
        // to mimic what would happen after a post normally
        app(CardSyncRepository::class)->create([
            'card_id' => $newCard->get('id'),
            'status'  => 1,
        ]);

        $now = Carbon::now();
        $favoritedById = 1;

        $newData = [
            'id'                  => $newCard->get('id'),
            'url'                 => 'https://newurl.com',
            'title'               => 'Cool new url',
            'description'         => 'cool new Description',
            'content'             => 'cool new content',
            'created_at'          => $now,
            'updated_at'          => $now,
            'favorited_by'        => $favoritedById,
        ];

        $response = $this->client('PATCH', 'card/'.$newCard->get('id'), array_merge($newData, ['collections' => [1]]));
        self::assertEquals(204, $response->getStatusCode());
        $this->assertDatabaseHas('collection_cards', [
            'card_id'       => $newCard->get('id'),
            'collection_id' => 1,
        ]);

        $data = $newData;
        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_updated_at'] = $now->toDateTimeString();
        $data['total_favorites'] = 1;
        unset($data['created_at'], $data['updated_at'], $data['favorited_by']);

        $this->assertDatabaseHas('cards', $data);
        $this->assertDatabaseHas('card_favorites', [
            'card_id' => $newCard->get('id'),
            'user_id' => $favoritedById,
        ]);
        Queue::assertPushed(SaveCardDataInitial::class, 1);
        Queue::assertPushed(SaveCardData::class, 0);

        $this->client('PATCH', 'card/'.$newCard->get('id'), array_merge($newData, ['collections' => []]));
        $this->assertDatabaseMissing('collection_cards', [
            'card_id'       => $newCard->get('id'),
            'collection_id' => 1,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardExists(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();

        $firstUrl = 'http://testurl.com';
        $this->client('POST', 'card', ['url' => $firstUrl]);
        $response = $this->client('POST', 'card', ['url' => 'http://different.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $response = $this->client('PATCH', 'card/'.$cardId, ['url' => $firstUrl]);

        self::assertEquals('exists', $this->getResponseData($response, 'error')->get('error'));
    }

    public function testCreateCardExists(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();

        $firstUrl = 'http://testurl.com';
        $this->client('POST', 'card', ['url' => $firstUrl]);
        $response = $this->client('POST', 'card', ['url' => $firstUrl]);

        self::assertSame($response->json()['data']['url'], $firstUrl);
    }

    /**
     * @throws JsonException
     * @throws BindingResolutionException
     */
    public function testCheckAuthedTest(): void
    {
        $this->mock(ExtractDataHelper::class);
        $website = app(WebsiteFactory::class)->make('hello');
        $this->mock(WebsiteExtractionHelper::class, static function ($mock) use ($website) {
            $mock->shouldReceive('simpleFetch')->andReturn($website);
            $mock->shouldReceive('parseHtml')->andReturn(collect([
               'image'    => '1',
               'author'   => '1',
               'excerpt'  => '1',
               'title'    => '1',
               'html'     => '1',
            ]));
        });

        $response = $this->client('POST', 'extension/check-authed', [
            'url'     => 'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/max',
            'rawHtml' => '<div></div>',
        ]);
        $response = $this->getResponseData($response);
        self::assertEquals(false, $response->get('isAuthed'));
    }

    /**
     * @throws JsonException
     */
    public function testCheckAuthedTestCurlFailed(): void
    {
        $this->mock(ExtractDataHelper::class);
        $this->mock(WebsiteExtractionHelper::class, static function ($mock) {
            $mock->shouldReceive('simpleFetch')->andThrow(new ParseException());
        });

        $response = $this->client('POST', 'extension/check-authed', [
            'url'     => 'https://www.w3schools.com/php/func_string_levenshtein.asp',
            'rawHtml' => '<div></div>',
        ]);
        $response = $this->getResponseData($response);
        self::assertEquals(true, $response->get('isAuthed'));
    }

    /**
     * @throws JsonException
     */
    public function testGetImageWithContent(): void
    {
        Queue::fake();
        $this->mock(ExtractDataHelper::class);

        $firstUrl = 'http://testurl.com';
        $fakeContent = <<<FakeContent
<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" lang="en-US"><head>
<style type="text/css"> .ttfm2{font-family: 'Lora';font-size:1.6em;line-height:1.6em;color:#2d2a2b;}  .ttfm3{font-family: 'Karla';font-size:1.6em;line-height:1.6em;color:#2d2a2b;}  .ttfm4{font-family: 'Karla';font-size:1.1em;line-height:1.1em;color:#2d2a2b;}  .ttfm5{font-family: 'Merriweather';font-size:1.1em;line-height:1.1em;color:#2d2a2b;}  .ttfm6{font-family: 'Lora';font-size:0.9em;line-height:0.9em;color:#2d2a2b;}  .ttfm7{font-family: 'Lora';font-size:0.9em;line-height:0.9em;color:#2d2a2b;} link .ttfm8{font-family: 'Karla';font-size:1.6em;line-height:1.6em;color:#2d2a2b;} </style> <!--[if lt IE 9]>
	<script src="https://149350408.v2.pressablecdn.com/wp-content/themes/squared/js/html5/dist/html5shiv.js"></script>
	<script src="//css3-mediaqueries-js.googlecode.com/svn/trunk/css3-mediaqueries.js"></script>
	<![endif]-->
<!--[if IE 8]>
	<link rel="stylesheet" type="text/css" href="https://149350408.v2.pressablecdn.com/wp-content/themes/squared/css/ie8.css"/>
	<![endif]-->
<!--[if IE 7]>
	<link rel="stylesheet" type="text/css" href="https://149350408.v2.pressablecdn.com/wp-content/themes/squared/css/ie7.css"/>
	<![endif]-->
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta charset="UTF-8" />

<script type="text/javascript" async="" id="gauges-tracker" data-site-id="5c014d80701bf42a5ee6453d" src="//secure.gaug.es/track.js"></script><script async="" src="https://s.adroll.com/j/XEEJRKTFHNEDTLRWONBGSE/roundtrip.js"></script><script async="" src="https://ducttapemarketing.com/wp-content/cache/busting/google-tracking/ga-53ee95b384d866e8692bb1aef923b763.js"></script><script async="" src="https://ducttapemarketing.com/wp-content/cache/busting/facebook-tracking/fbpix-events-en_US-2.9.33.js"></script><script type="text/javascript" async="" src="//bant.io/5fc799255d15f?random=1613230508736"></script><script type="text/javascript">
(function() {
    var s = document.createElement("script");
    s.type = "text/javascript";
    s.async = true;
    s.src = "//bant.io/5fc799255d15f?random=" + new Date().getTime();
    var x = document.getElementsByTagName("script")[0];
    x.parentNode.insertBefore(s, x);
})();
</script>


<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://ducttapemarketing.com/wp-content/cache/busting/facebook-tracking/fbpix-events-en_US-2.9.33.js');
fbq('init', '489621257873936');
fbq('track', 'PageView');
</script>
<noscript>&lt;img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=489621257873936&amp;ev=PageView&amp;noscript=1"
/&gt;</noscript>



<script type="text/javascript">
	var trackcmp_email = '';
	var trackcmp = document.createElement("script");
	trackcmp.async = true;
	trackcmp.type = 'text/javascript';
	trackcmp.src = '//trackcmp.net/visit?actid=999801188&amp;e='+encodeURIComponent(trackcmp_email)+'&amp;r='+encodeURIComponent(document.referrer)+'&amp;u='+encodeURIComponent(window.location.href);
	var trackcmp_s = document.getElementsByTagName("script");
	if (trackcmp_s.length) {
		trackcmp_s[0].parentNode.appendChild(trackcmp);
	} else {
		var trackcmp_h = document.getElementsByTagName("head");
		trackcmp_h.length &amp;&amp; trackcmp_h[0].appendChild(trackcmp);
	}
</script><script async="" type="text/javascript" src="//trackcmp.net/visit?actid=999801188&amp;e=&amp;r=https%3A%2F%2Fwww.groovehq.com%2F&amp;u=https%3A%2F%2Fducttapemarketing.com%2Fcontent-customer-journey%2F"></script>


<script data-no-minify="1" async="" src="https://ducttapemarketing.com/wp-content/cache/busting/1/gtm-73d64b7c582058e54682d5542b3dd34f.js"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-91481-1');
</script>

<script async="" src="https://www.googletagmanager.com/gtag/js?id=G-B6MGPY0YRB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-B6MGPY0YRB');
</script>

<title>How to Develop Content for Every Stage of the Customer Journey</title><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Karla%3A400%2C700%7CMerriweather%3A400%2C700%7CLora%3A400%2C400italic%2C700italic%2C700%7CMerriweather%3A400%2C400italic%2C700&amp;display=swap" data-rocket-async="style" as="style" onload="this.onload=null;this.rel='stylesheet'" /><link rel="stylesheet" href="https://ducttapemarketing.com/wp-content/cache/min/1/eed7c9990254e0603658471d5eac0b86.css" data-rocket-async="style" as="style" onload="this.onload=null;this.rel='stylesheet'" media="all" data-minify="1" />
<meta name="description" content="As the core of your strategy, you can not view content as a bunch of one-off projects. The creation of it needs to come out of one comprehensive strategy." />
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<link rel="canonical" href="https://ducttapemarketing.com/content-customer-journey/" />
<meta property="og:locale" content="en_US" />
<meta property="og:type" content="article" />
<meta property="og:title" content="How to Develop Content for Every Stage of the Customer Journey" />
<meta property="og:description" content="As the core of your strategy, you can not view content as a bunch of one-off projects. The creation of it needs to come out of one comprehensive strategy." />
<meta property="og:url" content="https://ducttapemarketing.com/content-customer-journey/" />
<meta property="og:site_name" content="Duct Tape Marketing" />
<meta property="article:publisher" content="https://facebook.com/ducttapemarketing" />
<meta property="article:published_time" content="2017-08-29T11:36:47+00:00" />
<meta property="article:modified_time" content="2020-01-03T04:39:09+00:00" />
<meta property="og:image" content="https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey.jpg" />
<meta property="og:image:width" content="800" />
<meta property="og:image:height" content="450" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:creator" content="@ducttape" />
<meta name="twitter:site" content="@ducttape" />
<meta name="twitter:label1" content="Written by" />
<meta name="twitter:data1" content="John Jantsch" />
<meta name="twitter:label2" content="Est. reading time" />
<meta name="twitter:data2" content="5 minutes" />

<link rel="dns-prefetch" href="//fonts.googleapis.com" />
<link rel="dns-prefetch" href="//ducttapemarketing.com" />
<link rel="dns-prefetch" href="//ajax.cloudflare.com" />
<link rel="dns-prefetch" href="//149350408.v2.pressablecdn.com" />
<link rel="dns-prefetch" href="//trackcmp.net" />
<link rel="dns-prefetch" href="//secure.gaug.es" />
<link href="https://fonts.gstatic.com" crossorigin="" rel="preconnect" />
<style type="text/css">
img.wp-smiley,
img.emoji {
	display: inline !important;
	border: none !important;
	box-shadow: none !important;
	height: 1em !important;
	width: 1em !important;
	margin: 0 .07em !important;
	vertical-align: -0.1em !important;
	background: none !important;
	padding: 0 !important;
}
</style>
<style type="text/css">
.powerpress_player .wp-audio-shortcode { max-width: 500px; }
</style>
<style id="rocket-lazyload-inline-css" type="text/css">
.rll-youtube-player{position:relative;padding-bottom:56.23%;height:0;overflow:hidden;max-width:100%;}.rll-youtube-player iframe{position:absolute;top:0;left:0;width:100%;height:100%;z-index:100;background:0 0}.rll-youtube-player img{bottom:0;display:block;left:0;margin:auto;max-width:100%;width:100%;position:absolute;right:0;top:0;border:none;height:auto;cursor:pointer;-webkit-transition:.4s all;-moz-transition:.4s all;transition:.4s all}.rll-youtube-player img:hover{-webkit-filter:brightness(75%)}.rll-youtube-player .play{height:72px;width:72px;left:50%;top:50%;margin-left:-36px;margin-top:-36px;position:absolute;background:url(https://149350408.v2.pressablecdn.com/wp-content/plugins/wp-rocket/assets/img/youtube.png) no-repeat;cursor:pointer}
</style>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-includes/js/jquery/jquery.min.js" id="jquery-core-js"></script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-includes/js/jquery/jquery-migrate.min.js" id="jquery-migrate-js"></script>
<link rel="https://api.w.org/" href="https://ducttapemarketing.com/wp-json/" /><link rel="alternate" type="application/json" href="https://ducttapemarketing.com/wp-json/wp/v2/posts/40868" /><link rel="EditURI" type="application/rsd+xml" title="RSD" href="https://ducttapemarketing.com/xmlrpc.php?rsd" />
<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="https://ducttapemarketing.com/wp-includes/wlwmanifest.xml" />
<link rel="shortlink" href="https://ducttapemarketing.com/?p=40868" />

<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','https://ducttapemarketing.com/wp-content/cache/busting/google-tracking/ga-53ee95b384d866e8692bb1aef923b763.js','ga');
			ga('create', 'UA-91481-1', 'auto');
			ga('send', 'pageview');
		</script>
<script type="application/ld+json" data-schema="40868-post-Default">{"@context":"https:\/\/schema.org\/","@type":"BlogPosting","@id":"https:\/\/ducttapemarketing.com\/content-customer-journey\/#BlogPosting","mainEntityOfPage":"https:\/\/ducttapemarketing.com\/content-customer-journey\/","headline":"How to Develop Content for Every Stage of the Customer Journey","name":"How to Develop Content for Every Stage of the Customer Journey","description":"As the core of your strategy, you can not view content as a bunch of one-off projects. The creation of it needs to come out of one comprehensive strategy.","datePublished":"2017-08-29","dateModified":"2020-01-02","author":{"@type":"Person","@id":"https:\/\/ducttapemarketing.com\/author\/johnjantsch\/#Person","name":"John Jantsch","url":"https:\/\/ducttapemarketing.com\/author\/johnjantsch\/","image":{"@type":"ImageObject","@id":"https:\/\/secure.gravatar.com\/avatar\/42ae44eb084696507dbc21c114c82d4c?s=96&amp;d=mm&amp;r=g","url":"https:\/\/secure.gravatar.com\/avatar\/42ae44eb084696507dbc21c114c82d4c?s=96&amp;d=mm&amp;r=g","height":96,"width":96}},"publisher":{"@type":"Organization","name":"Duct Tape Marketing","logo":{"@type":"ImageObject","@id":"https:\/\/www.ducttapemarketing.com\/wp-content\/uploads\/2018\/02\/Duct-Tape-Marketing-Logo.png","url":"https:\/\/www.ducttapemarketing.com\/wp-content\/uploads\/2018\/02\/Duct-Tape-Marketing-Logo.png","width":200,"height":90}},"image":{"@type":"ImageObject","@id":"https:\/\/ducttapemarketing.com\/wp-content\/uploads\/2017\/08\/customer-journey.jpg","url":"https:\/\/ducttapemarketing.com\/wp-content\/uploads\/2017\/08\/customer-journey.jpg","height":450,"width":800},"url":"https:\/\/ducttapemarketing.com\/content-customer-journey\/","about":["Content Marketing","Strategy First"],"wordCount":1192,"keywords":["Content","customer journey","Marketing Hourglass"]}</script>
<style type="text/css">:not(#tve) .ttfm2{font-family: 'Lora' !important;color: #2d2a2b;font-weight: 700 !important;}.ttfm2 input, .ttfm2 select, .ttfm2 textarea, .ttfm2 button {font-family: 'Lora' !important;color: #2d2a2b;font-weight: 700 !important;}:not(#tve) .ttfm3{font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}.ttfm3 input, .ttfm3 select, .ttfm3 textarea, .ttfm3 button {font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}:not(#tve) .ttfm3.bold_text,.ttfm3 .bold_text,.ttfm3 b,.ttfm3 strong{font-weight: 700 !important;}.ttfm3.bold_text,.ttfm3 .bold_text,.ttfm3 b,.ttfm3 strong input, .ttfm3.bold_text,.ttfm3 .bold_text,.ttfm3 b,.ttfm3 strong select, .ttfm3.bold_text,.ttfm3 .bold_text,.ttfm3 b,.ttfm3 strong textarea, .ttfm3.bold_text,.ttfm3 .bold_text,.ttfm3 b,.ttfm3 strong button {font-weight: 700 !important;}:not(#tve) .ttfm4{font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}.ttfm4 input, .ttfm4 select, .ttfm4 textarea, .ttfm4 button {font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}:not(#tve) .ttfm4.bold_text,.ttfm4 .bold_text,.ttfm4 b,.ttfm4 strong{font-weight: 700 !important;}.ttfm4.bold_text,.ttfm4 .bold_text,.ttfm4 b,.ttfm4 strong input, .ttfm4.bold_text,.ttfm4 .bold_text,.ttfm4 b,.ttfm4 strong select, .ttfm4.bold_text,.ttfm4 .bold_text,.ttfm4 b,.ttfm4 strong textarea, .ttfm4.bold_text,.ttfm4 .bold_text,.ttfm4 b,.ttfm4 strong button {font-weight: 700 !important;}:not(#tve) .ttfm5{font-family: 'Merriweather' !important;color: #2d2a2b;font-weight: 400 !important;}.ttfm5 input, .ttfm5 select, .ttfm5 textarea, .ttfm5 button {font-family: 'Merriweather' !important;color: #2d2a2b;font-weight: 400 !important;}:not(#tve) .ttfm5.bold_text,.ttfm5 .bold_text,.ttfm5 b,.ttfm5 strong{font-weight: 700 !important;}.ttfm5.bold_text,.ttfm5 .bold_text,.ttfm5 b,.ttfm5 strong input, .ttfm5.bold_text,.ttfm5 .bold_text,.ttfm5 b,.ttfm5 strong select, .ttfm5.bold_text,.ttfm5 .bold_text,.ttfm5 b,.ttfm5 strong textarea, .ttfm5.bold_text,.ttfm5 .bold_text,.ttfm5 b,.ttfm5 strong button {font-weight: 700 !important;}:not(#tve) .ttfm6{font-family: 'Lora' !important;color: #2d2a2b;font-weight: 400 !important;}.ttfm6 input, .ttfm6 select, .ttfm6 textarea, .ttfm6 button {font-family: 'Lora' !important;color: #2d2a2b;font-weight: 400 !important;}:not(#tve) .ttfm6.bold_text,.ttfm6 .bold_text,.ttfm6 b,.ttfm6 strong{font-weight: 700 !important;}.ttfm6.bold_text,.ttfm6 .bold_text,.ttfm6 b,.ttfm6 strong input, .ttfm6.bold_text,.ttfm6 .bold_text,.ttfm6 b,.ttfm6 strong select, .ttfm6.bold_text,.ttfm6 .bold_text,.ttfm6 b,.ttfm6 strong textarea, .ttfm6.bold_text,.ttfm6 .bold_text,.ttfm6 b,.ttfm6 strong button {font-weight: 700 !important;}:not(#tve) .ttfm7{font-family: 'Lora' !important;color: #2d2a2b;font-weight: 700 !important;}.ttfm7 input, .ttfm7 select, .ttfm7 textarea, .ttfm7 button {font-family: 'Lora' !important;color: #2d2a2b;font-weight: 700 !important;}:not(#tve) .ttfm8{font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}.ttfm8 input, .ttfm8 select, .ttfm8 textarea, .ttfm8 button {font-family: 'Karla' !important;color: #2d2a2b;font-weight: 400 !important;}:not(#tve) .ttfm8.bold_text,.ttfm8 .bold_text,.ttfm8 b,.ttfm8 strong{font-weight: 700 !important;}.ttfm8.bold_text,.ttfm8 .bold_text,.ttfm8 b,.ttfm8 strong input, .ttfm8.bold_text,.ttfm8 .bold_text,.ttfm8 b,.ttfm8 strong select, .ttfm8.bold_text,.ttfm8 .bold_text,.ttfm8 b,.ttfm8 strong textarea, .ttfm8.bold_text,.ttfm8 .bold_text,.ttfm8 b,.ttfm8 strong button {font-weight: 700 !important;}</style><style type="text/css" id="tve_global_variables">:root{--tcb-color-0:rgb(45, 42, 43);--tcb-color-1:rgb(240, 115, 76);--tcb-color-2:rgb(26, 40, 84);}</style><style type="text/css">.wp-video-shortcode {max-width: 100% !important;}body { background:#ffffff; }.cnt .sAs .twr { background:#ffffff; }.cnt article h1.entry-title a { color:#302e2f; }.cnt article h2.entry-title a { color:#302e2f; }.bSe h1,.bSe h2.entry-title { color:#302e2f; }.bSe h5 { color:#302e2f; }.bSe h6 { color:#302e2f; }.cnt article p { color:#302e2f; }.cnt .bSe article { color:#302e2f; }.cnt article h1 a, .tve-woocommerce .bSe .awr .entry-title, .tve-woocommerce .bSe .awr .page-title{font-family:Lora,sans-serif;}.bSe h1,.bSe h2.entry-title{font-family:Lora,sans-serif;}.bSe h2,.tve-woocommerce .bSe h2{font-family:Lora,sans-serif;}.bSe h3,.tve-woocommerce .bSe h3{font-family:Lora,sans-serif;}.bSe h4{font-family:Lora,sans-serif;}.bSe h5{font-family:Lora,sans-serif;}.bSe h6{font-family:Lora,sans-serif;}#text_logo{font-family:Lora,sans-serif;}.cnt, .cnt article p, .bp-t, .tve-woocommerce .product p, .tve-woocommerce .products p{font-family:Merriweather,sans-serif;font-weight:400;}article strong {font-weight: bold;}.bSe h1,.bSe h2.entry-title, .hru h1, .bSe .entry-title { font-size:45px!important; }.cnt { font-size:17px; }.thrivecb { font-size:17px; }.out { font-size:17px; }.aut p { font-size:17px; }.cnt p { line-height:1.6em; }.lhgh { line-height:1.6em; }.dhgh { line-height:1.6em; }.lhgh { line-height:1.6em; }.dhgh { line-height:1.6em; }.thrivecb { line-height:1.6em; }.bSe a, .cnt article a { color:#0c71af; }.bSe .faq h4{font-family:Merriweather,sans-serif;font-weight:400;}article strong {font-weight: bold;}header ul.menu &gt; li &gt; a { color:#e6e6e6; }header ul.menu &gt; li &gt;  a:hover { color:#a0d8f8; }header nav &gt; ul &gt; li.current_page_item &gt; a:hover { color:#a0d8f8; }header nav &gt; ul &gt; li.current_menu_item &gt; a:hover { color:#a0d8f8; }header nav &gt; ul &gt; li.current_menu_item &gt; a:hover { color:#a0d8f8; }header nav &gt; ul &gt; li &gt; a:active { color:#a0d8f8; }header #logo &gt; a &gt; img { max-width:200px; }header ul.menu &gt; li.h-cta &gt; a { color:#FFFFFF!important; }header ul.menu &gt; li.h-cta &gt; a { background:#a0d8f8; }header ul.menu &gt; li.h-cta &gt; a { border-color:#86bede; }header ul.menu &gt; li.h-cta &gt; a:hover { color:#FFFFFF!important; }header ul.menu &gt; li.h-cta &gt; a:hover { background:#acddf9; }header ul.menu &gt; li.h-cta &gt; a:hover { border-color:#92c3df; }</style><style type="text/css">.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style><style type="text/css" id="custom-background-css">
body.custom-background { background-color: #ffffff; }
</style>
<link rel="amphtml" href="https://ducttapemarketing.com/content-customer-journey/amp/" /><style type="text/css" id="thrive-default-styles"></style><link rel="icon" href="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/11/cropped-ducttapefav-32x32.png" sizes="32x32" />
<link rel="icon" href="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/11/cropped-ducttapefav-192x192.png" sizes="192x192" />
<link rel="apple-touch-icon" href="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/11/cropped-ducttapefav-180x180.png" />
<meta name="msapplication-TileImage" content="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/11/cropped-ducttapefav-270x270.png" />
<style type="text/css" id="wp-custom-css">
			.page-id-19548 .tve-leads-track-shortcode_35149 input::-webkit-input-placeholder { /* Chrome/Opera/Safari */
  color: #58595b !important;
}
.page-id-19548 .tve-leads-track-shortcode_35149 input::-moz-placeholder { /* Firefox 19+ */
  color: #58595b !important;
}
.page-id-19548 .tve-leads-track-shortcode_35149 input:-ms-input-placeholder { /* IE 10+ */
 color: #58595b !important;
}
.page-id-19548 .tve-leads-track-shortcode_35149 input:-moz-placeholder { /* Firefox 18- */
  color: #58595b !important;
}
a.emailbutton {
    border: 2px solid #1b75bc;
    border-radius: 4px 4px 4px 4px !important;
    -moz-border-radius: 4px 4px 4px 4px !important;
    -webkit-border-radius: 4px 4px 4px 4px !important;
    box-shadow: none !important;
    border-bottom-color: #1b75bc !important;
    -webkit-transition: all 0.3s ease-in-out 0s;
    transition: all 0.3s ease-in-out 0s;
    background: #1b75bc;
    cursor: pointer;
    padding: 20px 40px !important;
    font-size: 22px !important;
    line-height: 22px !important;
    font-family: 'Merriweather', serif;
    font-weight: 300;
    margin: 0 auto;
    display: block;
	margin-top:20px;background-color: #00aeef !important;border-color: #00aeef !important;color: #FFF !important;
}

/* Shortcode main menu*/
.wrp.consultant a{
	display: block;
    float: right;
    margin-top: -20px;
    border: 1.5px solid #FFF;
    padding: 0px 10px;
    border-radius: 5px;
    font-size: 16px;
    color: #FFF;
    -webkit-transition: all .2s;
    transition: all .2s;
    position: relative;
    z-index: 999;
    line-height: 27.2px;
}
.wrp.consultant a:hover{
	    background: #FFF;
    color: #bbbbbb;
    -webkit-transition: all .2s;
    transition: all .2s;
}
nav#main-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
	max-width: 95%;
    margin: 0 auto;
}
#main-nav .menu-open{
	order:1;
}
.consultants-template-default
.wrp.menu-open {
    width: initial!important;
    margin: initial;
}
#main-nav .wrp.consultant,
#main-nav .blog_search{
	order:3;
}
#main-nav &gt; a{
	order:2;
}
#main-nav .menu-open,
.wrp.consultant,
#main-nav .blog_search{
    padding: initial!important;
    position: relative!important;
    top: initial!important;
    left: initial!important;
}
#main-nav svg.logo{
	margin:10px auto 10px
}

#main-nav &gt; a {
    float: inherit;
}

#tve_flt #main-nav .wrp.consultant {
	    top: 25px !important;
}

.no-p-spacing p {
	margin-bottom: 0 !important;
}

@media (max-width:640px){
	#main-nav &gt; a {
    top:50px;
}

	nav#main-nav{
		   flex-wrap: wrap;
		    margin-bottom: 25px;
	}

	#main-nav .menu-open, .wrp.consultant,
#main-nav .blog_search {
    text-align: center;
    width: 50%;
}
	#main-nav .menu-open{
		order:2;
	}
	#main-nav &gt; a{
	order:1;
		width:100%;
}
	.wrp.consultant a,
	.bSe .wrp.consultant a{
		float:unset!important;
		margin:0 auto!important;
		display:block!important;
		width:fit-content!important;
	}
	#main-nav .menu-open,
	#main-nav .wrp.consultant,
#main-nav .blog_search{
		width:50%;
	}
	#main-nav .menu-open, #main-nav .wrp.consultant,
#main-nav .blog_search {
    margin-top: 25px;
}
	#main-nav .menu-open,
	.wrp.consultant,
#main-nav .blog_search{
		width:100%;
		margin-top:10px;
	}
}

#main-nav &gt; a {
    float: inherit;
}

#tve_flt #main-nav .wrp.consultant {
	    top: 25px !important;
}

.home .tcb-post-title, .home .tcb-post-title a{
	padding: 0 !important;
	min-height: 0 !important;
}
		</style>
<noscript>&lt;style id="rocket-lazyload-nojs-css"&gt;.rll-youtube-player, [data-lazy-src]{display:none !important;}&lt;/style&gt;</noscript><script>
/*! loadCSS rel=preload polyfill. [c]2017 Filament Group, Inc. MIT License */
(function(w){"use strict";if(!w.loadCSS){w.loadCSS=function(){}}
var rp=loadCSS.relpreload={};rp.support=(function(){var ret;try{ret=w.document.createElement("link").relList.supports("preload")}catch(e){ret=!1}
return function(){return ret}})();rp.bindMediaToggle=function(link){var finalMedia=link.media||"all";function enableStylesheet(){link.media=finalMedia}
if(link.addEventListener){link.addEventListener("load",enableStylesheet)}else if(link.attachEvent){link.attachEvent("onload",enableStylesheet)}
setTimeout(function(){link.rel="stylesheet";link.media="only x"});setTimeout(enableStylesheet,3000)};rp.poly=function(){if(rp.support()){return}
var links=w.document.getElementsByTagName("link");for(var i=0;i&lt;links.length;i++){var link=links[i];if(link.rel==="preload"&amp;&amp;link.getAttribute("as")==="style"&amp;&amp;!link.getAttribute("data-loadcss")){link.setAttribute("data-loadcss",!0);rp.bindMediaToggle(link)}}};if(!rp.support()){rp.poly();var run=w.setInterval(rp.poly,500);if(w.addEventListener){w.addEventListener("load",function(){rp.poly();w.clearInterval(run)})}else if(w.attachEvent){w.attachEvent("onload",function(){rp.poly();w.clearInterval(run)})}}
if(typeof exports!=="undefined"){exports.loadCSS=loadCSS}
else{w.loadCSS=loadCSS}}(typeof global!=="undefined"?global:this))
</script>
</head>
<body class="post-template-default single single-post postid-40868 single-format-standard custom-background">
<div class="flex-cnt"><div id="background" style="height: 790.5px;"></div>
<div id="floating_menu">
<header class="" style="">
<div class="side_logo wrp " id="head_wrp">
<div class="h-i">
<div id="logo" class="left">
<a href="https://ducttapemarketing.com/" class="lg">
<img src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/02/Duct-Tape-Marketing-Logo.png" alt="Duct Tape Marketing" class="lazyloaded" data-ll-status="loaded" /><noscript>&lt;img src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2018/02/Duct-Tape-Marketing-Logo.png"
											 alt="Duct Tape Marketing"/&gt;</noscript>
</a>
</div>
<div class="hmn">
<div class="awe rmn right"></div>
<div class="clear"></div>
</div>
<div class="mhl right" id="nav_right">

<nav class="right"><ul id="menu-main-menu" class="menu"><li id="menu-item-54873" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-home toplvl"><a href="https://ducttapemarketing.com/">Home</a></li>
<li id="menu-item-54874" class="menu-item menu-item-type-post_type menu-item-object-page toplvl"><a href="https://ducttapemarketing.com/about/company/">About</a></li>
<li id="menu-item-54875" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children toplvl dropdown"><a href="#">Services</a><ul class="sub-menu"> <li id="menu-item-54879" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/certified-marketing-manager/" class=" colch ">Marketing Training For Your Team</a></li>
<li id="menu-item-54986" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/marketing-consultants/" class=" colch ">Marketing Consultant Network</a></li>
<li id="menu-item-54938" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/how-to-build-a-local-marketing-system/" class=" colch ">Done For You Marketing</a></li>
</ul></li>
<li id="menu-item-54881" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children toplvl dropdown"><a href="#">Resources</a><ul class="sub-menu"> <li id="menu-item-54882" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/blog/" class=" colch ">Blog</a></li>
<li id="menu-item-54885" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/duct-tape-marketing-podcast/" class=" colch ">Podcast</a></li>
<li id="menu-item-54884" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/books/" class=" colch ">Books</a></li>
<li id="menu-item-54886" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/hire-john-to-speak/" class=" colch ">Speaking</a></li>
<li id="menu-item-54939" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/find-certified-consultant/" class=" colch ">Find a Consultant</a></li>
</ul></li>
<li id="menu-item-54887" class="button menu-item menu-item-type-post_type menu-item-object-page toplvl"><a href="https://ducttapemarketing.com/about/contact-duct-tape-marketing/">Let’s Talk</a></li>
</ul></nav> </div>
<div class="clear"></div>
</div>
</div>
</header>
</div>
<div class="hru tcbk" style="background-color: rgb(255, 255, 255);">
<div class="hrui">
<div class="wrp">
<h1>
How to Develop Content for Every Stage of the Customer Journey </h1>
<div class="hcc" style="display:none;">
<a href="#comments">
0 Comments </a>
</div>
</div>
</div>
</div>
<div class="wrp cnt">
<section class="bSe fullWidth">
<div class="awr lnd">
<div id="tve_flt" class="tve_flt">
<div id="tve_editor" class="tve_shortcode_editor">
<article>
<div class="thrv_wrapper thrv_page_section" data-tve-style="1">
<div class="pswr out">
<div class="in darkSec pddbg" style="max-width: 1777px;">
<div class="cck tve_clearfix tve_empty_dropzone">
<div class="blog_search">
<form action="https://ducttapemarketing.com/" method="get" class="srh">
<button type="submit" id="search-button" class="search-button sBn"><span data-tve-icon="icon-uniF002" class="tve_sc_icon icon-uniF002" data-tve-custom-colour="44801797"></span></button>
<input type="text" id="search-field" class="search-field" placeholder="Search Blog..." name="s" />
</form>
</div></div>
</div>
</div>
</div>
<div class="awr lnd hfp">
<div class="heading">
<div class="thrv_wrapper thrv_button_shortcode tve_leftBtn blog" data-tve-style="1">
<div class="tve_btn tve_nb tve_bigBtn tve_red tve_btn1">
<a href="https://ducttapemarketing.com/blog/" class="tve_btnLink ttfm3">
<span class="tve_btn_txt">&lt; All Articles</span>
</a>
</div>
</div>
<h1>How to Develop Content for Every Stage of the Customer Journey</h1>
<p class="author">By John Jantsch</p>
<div class="dtm-social"><h5>Share</h5><a class="dtm-link dtm-twitter" href="https://twitter.com/intent/tweet?text=How%20to%20Develop%20Content%20for%20Every%20Stage%20of%20the%20Customer%20Journey&amp;url=https%3A%2F%2Fducttapemarketing.com%2Fcontent-customer-journey%2F" target="_blank"></a><a class="dtm-link dtm-facebook" href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fducttapemarketing.com%2Fcontent-customer-journey%2F" target="_blank"></a><a class="dtm-link dtm-linkedin" href="https://www.linkedin.com/shareArticle?mini=true&amp;url=https%3A%2F%2Fducttapemarketing.com%2Fcontent-customer-journey%2F&amp;title=How%20to%20Develop%20Content%20for%20Every%20Stage%20of%20the%20Customer%20Journey" target="_blank"></a></div> <img class="featured-img lazyloaded" src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey.jpg" alt="customer journey" sizes="(max-width: 1080px) 72w, 1000px" srcset="https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey.jpg 800w , https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey-300x169.jpg 300w , https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey-768x432.jpg 768w " data-ll-status="loaded" /><noscript>&lt;img class="featured-img" src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey.jpg"
             srcset="https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey.jpg 800w , https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey-300x169.jpg 300w , https://149350408.v2.pressablecdn.com/wp-content/uploads/2017/08/customer-journey-768x432.jpg 768w "
             sizes="(max-width: 1080px) 72w, 1000px" alt="customer journey"&gt;</noscript>
</div>
<div id="content" class="three-quarter clearfix"><p>For marketers, it’s nearly impossible to get through a day without hearing about or discussing content in one way or another. As the core of your strategy, you can not view content as a bunch of one-off projects. The creation of it needs to come out of one comprehensive strategy.</p>
<p>Because it is such an important piece of the marketing puzzle these days, it needs to be incorporated in every phase of the customer journey. While people often split this journey into three phases, Awareness, Consideration, and Content, I believe there is a bit more to it than that, which is why I’ve developed <a href="https://www.ducttapemarketing.com/blog/2010/12/07/7-little-words-that-sum-up-the-entire-marketing-machine/" target="_blank" rel="noopener noreferrer">the Marketing Hourglass</a> which consists of seven stages: Know, Like, Trust, Try, Buy, Repeat, and Refer. These phases will get a person from their first encounter with your business and then past your point of purchase where they not only turn into a customer but a loyal fan and advocate for your business.</p>
<p>As a person moves through the customer journey, you must hit them with content throughout the process to keep them engaged with your business, and the best way to do this is to match the content you’d like to develop with the various phases of the Marketing Hourglass.</p>
<h2>Mapping the Customer Journey</h2>
<p>When it comes to the <a href="https://ducttapemarketingconsultant.com/guiding-the-customer-journey/">customer journey</a>, it’s important that you don’t get ahead of yourself. I often see small businesses trying to convince prospects that they can solve their problems before they even know they have one.</p>
<p>In order to map out your customer journey, you must understand who your audience is, and I mean <em>really understand </em>their wants, needs and pain points, as well as the types of questions they’d ask themselves before they even seek a solution like yours.</p>
<p>You must be aware of what your customer’s journey looks like in order to develop content for each stage of it. To help you do so, I’ve described the stages below as well as recommended content to go along with each stage to help you brainstorm what would work best for your business.</p>
<h2>Know</h2>
<p>The Know stage is the phase where people first become aware of your business, and it’s your job to put a piece of content out there that get’s their attention.</p>
<p>Types of content:</p>
<ul>
<li><a href="https://www.ducttapemarketing.com/blog/">Blog posts</a> answering common client challenges (to help boost SEO)</li>
<li>Advertising (consider paid search and paid social) promoting content upgrades to boost lead conversion</li>
<li>Presentations at speaking engagements</li>
<li>Social media</li>
</ul>
<h2>Like</h2>
<p>Once you attract a person to your website, you enter the second stage of the Marketing Hourglass: Like. At this point, you need to give them reasons to keep wanting more and move towards gaining permission to continue the conversation.</p>
<p>Types of content:</p>
<ul>
<li>eNewsletters for lead nurturing and to demonstrate expertise, knowledge, and resources over time</li>
<li>Blog content around specific topics</li>
<li>Social media</li>
<li><a href="https://www.ducttapemarketing.com/monthly-training/">Webinars</a></li>
<li>White papers</li>
</ul>
<h2>Trust</h2>
<p>I believe Trust is the most important step but arguably the most tedious and time-consuming. Building trust is a marathon, not a sprint. The more a person trusts you and your company, the more likely they’ll be to buy from you.</p>
<p>Types of content:</p>
<ul>
<li>Reviews</li>
<li>Success stories</li>
<li>Client testimonials</li>
<li>Webinars</li>
<li>Ebooks</li>
<li>Custom presentations</li>
<li>How tos</li>
<li>Client readiness packets</li>
<li>Proposal documents</li>
<li>Customer-generated videos</li>
<li>Case studies</li>
</ul>
<h2>Try</h2>
<p>If you’ve built trust to the point where people begin wondering how your solution might work for them, it’s time to enter the Try stage of the hourglass. Try is a phase that many people skip due to the desire to leap rather than lead, however, I think it’s the easiest phase to move people to the purchase.</p>
<p>Here, the content needs to represent a sample of the end result. By creating content in this phase that demonstrates how much better your product or service is than the competition, you can differentiate your business.</p>
<p>Types of content:</p>
<ul>
<li>Ebooks</li>
<li>Online or offline seminars</li>
<li>Webinars</li>
<li>Workshops</li>
<li><a href="https://www.ducttapemarketing.com/a-la-carte/online-audit/">Audits</a></li>
<li>Evaluations</li>
<li>Video demos</li>
<li>FAQs</li>
</ul>
<h2>Buy</h2>
<p>This is the step all businesses want, but you must look at it as just another stepping stone to growing your list of thrilled customers (who become brand advocates). For this stage, the focus is maintaining a good experience for the prospect. In order to continue to deliver a remarkable customer experience, you’ve got to continue to educate through content.</p>
<p>Types of content:</p>
<ul>
<li>New customer kits</li>
<li>Quick start guides</li>
<li>Customer stories</li>
<li>User manuals</li>
</ul>
<h2>Repeat</h2>
<p>To keep customers coming back time and time again, don’t wait for them to call you. You need to stay top of mind, and a great way to do this is to provide them with high-level content.</p>
<p>One of the best ways to get repeat business is to make sure your customers understand the value they receive by doing business with you. In the Repeat phase, you need to consider adding a results review process as well as additional upsell and cross sell touchpoints.</p>
<p>Types of content:</p>
<ul>
<li>Start an auto responder series that provides education on additional solutions</li>
<li>Handwritten notes for no reason</li>
<li>Send press clippings systematically</li>
<li>Customer-only newsletters</li>
</ul>
<h2>Refer</h2>
<p>The whole point of the Marketing Hourglass is to turn happy clients into referral clients. To do this, you must build processes and campaigns that make it easy for your brand champions to refer your business.</p>
<p>Types of content:</p>
<ul>
<li>eBooks, videos, or gift certificates that your customers and strategic partners can co-brand and distribute</li>
<li>Feature your client stories in your marketing materials</li>
<li>Create a hot 100 prospect list and share it with clients for introductions</li>
</ul>
<p>Keep in mind, you don’t always have to reinvent the wheel when it comes to content development. You can repurpose old content (i.e. turning educational videos into written blog posts) and you can even optimize and re-publish previous well-performing content to give it new life.</p>
<p>Creating content can be time-consuming, but by mapping it out along with certain themes and the customer journey, your life will become much easier.</p>
<p>What types of content do you find helpful in each stage of the journey?</p>
<p>If you liked this post, check out our <a href="https://www.ducttapemarketing.com/small-business-content-marketing/">Guide to Content Marketing for Small Business</a> and the <a href="https://ducttapemarketing.com/small-business-guide-to-customer-journey/">Small Business Guide to Shaping the Customer Journey</a>.</p>
<div class="tve-leads-shortcode tve-leads-triggered tve-tl-anim tl-anim-instant tve-leads-track-shortcode_47490"><div class="tl-style" id="tve_tcb2_set-027" data-state="12" data-form-state=""><style type="text/css" class="tve_custom_style">@import url("//fonts.googleapis.com/css?family=Karla:700&amp;subset=latin");@import url("//fonts.googleapis.com/css?family=Lora:700&amp;subset=latin");@import url("//fonts.googleapis.com/css?family=Roboto:300,500,900,400&amp;subset=latin");@media (min-width: 300px){[data-css="tve-u-25e66a53209a73"] &gt; .tcb-flex-col { padding-left: 0px; }[data-css="tve-u-85e66a53209a7c"] { padding: 10px 10px 10px 0px !important; background-color: rgb(12, 113, 175) !important; }[data-css="tve-u-45e66a53209a76"] { background-color: rgb(12, 113, 175) !important; padding: 10px 0px 0px !important; border-radius: 0px; overflow: hidden; }[data-css="tve-u-55e66a53209a77"] { float: none; margin-left: auto; margin-right: auto; width: 174px; box-shadow: none; margin-bottom: 0px !important; margin-top: 10px !important; padding-left: 10px !important; }[data-css="tve-u-95e66a53209a7d"] { margin-bottom: 1px !important; }[data-css="tve-u-25e66a53209a73"] { margin-left: 0px; padding: 0px !important; }[data-css="tve-u-15e66a53209a72"] { margin: 0px !important; }[data-css="tve-u-05e66a53209a6e"] { overflow: hidden; background-color: rgb(42, 56, 115) !important; border-radius: 0px !important; padding: 0px !important; }[data-css="tve-u-105e66a53209a7e"] { line-height: 36px !important; }:not(#tve) [data-css="tve-u-105e66a53209a7e"] { font-family: Roboto; font-weight: 300; color: rgb(255, 255, 255) !important; font-size: 32px !important; }[data-css="tve-u-75e66a53209a7a"] { max-width: 70%; }[data-css="tve-u-35e66a53209a75"] { max-width: 30%; }[data-css="tve-u-125e66a53209a81"] { font-size: 35px !important; font-family: Lora !important; }[data-css="tve-u-115e66a53209a80"] { font-size: 24px !important; font-family: Karla !important; }[data-css="tve-u-165e66a53209a86"] { margin-top: 10px !important; }[data-css="tve-u-165e66a53209a86"] .tcb-button-link { border-radius: 5px; overflow: hidden; background-image: none !important; background-color: rgb(240, 115, 76) !important; }:not(#tve) [data-css="tve-u-165e66a53209a86"]:hover .tcb-button-link { background-color: rgb(214, 103, 66) !important; }[data-css="tve-u-125e66a53209a81"] strong { font-weight: 700 !important; }[data-css="tve-u-105e66a53209a7e"] strong { font-weight: 700 !important; }:not(#tve) [data-css="tve-u-175e66a53209a87"] { font-family: Karla !important; --g-bold-weight:700; font-weight: var(--g-regular-weight, normal) !important; }[data-css="tve-u-175e66a53209a87"] strong { font-weight: 700 !important; }:not(#tve) [data-css="tve-u-165e66a53209a86"]:hover [data-css="tve-u-175e66a53209a87"] { color: rgb(250, 251, 251) !important; --tcb-applied-color:rgb(250, 251, 251) !important; }[data-css="tve-u-155e66a53209a84"] { --tcb-applied-color:rgb(250, 251, 251) !important; }[data-css="tve-u-135e66a53209a82"] { line-height: 36px !important; }:not(#tve) [data-css="tve-u-135e66a53209a82"] { font-family: Roboto; font-weight: 300; color: rgb(255, 255, 255) !important; font-size: 17px !important; line-height: 1.3em !important; }[data-css="tve-u-135e66a53209a82"] strong { font-weight: 700 !important; }[data-css="tve-u-145e66a53209a83"] { font-family: Lora !important; }[data-css="tve-u-145e66a53209a83"] strong { font-weight: 700 !important; }}@media (max-width: 767px){[data-css="tve-u-55e66a53209a77"] { width: 110px; margin: 10px auto !important; }[data-css="tve-u-05e66a53209a6e"] { padding: 0px !important; }[data-css="tve-u-85e66a53209a7c"] { text-align: center; }[data-css="tve-u-105e66a53209a7e"] { line-height: 1.2em !important; }:not(#tve) [data-css="tve-u-105e66a53209a7e"] { font-size: 28px !important; }[data-css="tve-u-135e66a53209a82"] { line-height: 1.2em !important; }:not(#tve) [data-css="tve-u-135e66a53209a82"] { font-size: 28px !important; }}</style><style type="text/css" class="tve_user_custom_style">.tve-leads-conversion-object .thrv_heading h1,.tve-leads-conversion-object .thrv_heading h2,.tve-leads-conversion-object .thrv_heading h3{margin:0;padding:0}.tve-leads-conversion-object .thrv_text_element p,.tve-leads-conversion-object .thrv_text_element h1,.tve-leads-conversion-object .thrv_text_element h2,.tve-leads-conversion-object .thrv_text_element h3{margin:0}</style><div class="tve-leads-conversion-object" data-tl-type="shortcode_47490"><div class="tve_flt"><div id="tve_editor" class="tve_shortcode_editor"><div class="thrv-leads-form-box tve_no_drag tve_no_icons thrv_wrapper tve_editor_main_content thrv-leads-in-content tve_empty_dropzone" style="" data-css="tve-u-05e66a53209a6e"><div class="thrv_wrapper thrv-columns" style="" data-css="tve-u-15e66a53209a72"><div class="tcb-flex-row tcb-resized tcb--cols--2" data-css="tve-u-25e66a53209a73"><div class="tcb-flex-col" data-css="tve-u-35e66a53209a75" style=""><div class="tcb-col tve_empty_dropzone" style="border-radius: 0px; overflow: hidden;" data-css="tve-u-45e66a53209a76"><div class="thrv_wrapper tve_image_caption img_style_lifted_style2" data-css="tve-u-55e66a53209a77" style=""><span class="tve_image_frame" style="width: 100%;"><img class="tve_image wp-image-51148 lazyloaded" alt="" width="406" height="528" title="No Overhead ebook" data-id="51148" src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2020/03/Screen-Shot-2020-03-09-at-8.19.31-AM.png" style="" data-css="tve-u-65e66a53209a79" data-ll-status="loaded" /><noscript>&lt;img class="tve_image wp-image-51148" alt="" width="406" height="528" title="No Overhead ebook" data-id="51148" src="https://149350408.v2.pressablecdn.com/wp-content/uploads/2020/03/Screen-Shot-2020-03-09-at-8.19.31-AM.png" style="" data-css="tve-u-65e66a53209a79"&gt;</noscript></span></div></div></div><div class="tcb-flex-col" data-css="tve-u-75e66a53209a7a" style=""><div class="tcb-col tve_empty_dropzone" style="" data-css="tve-u-85e66a53209a7c"><div class="thrv_wrapper thrv_text_element tve_empty_dropzone" style="" data-css="tve-u-95e66a53209a7d"><p data-css="tve-u-105e66a53209a7e" data-default="Enter your text here..."><span data-css="tve-u-115e66a53209a80" style="font-size: 24px;">Free eBook <br /></span><span data-css="tve-u-125e66a53209a81" style="font-size: 35px; font-family: Lora;">7 Steps to Scale Your Consulting Practice Without Adding Overhead</span></p><p data-css="tve-u-135e66a53209a82" data-default="Enter your text here..." style=""><span data-css="tve-u-145e66a53209a83" style="font-family: Lora;"><span data-css="tve-u-155e66a53209a84" style=""><em>"This training from Duct Tape Marketing has exceeded my expectations and I couldn't be happier" ~ </em></span><em>Brooke Patterson, VanderMedia</em></span></p></div><div class="thrv_wrapper thrv-button" data-css="tve-u-165e66a53209a86" data-tcb_hover_state_parent=""> <a href="https://ducttapemarketing.com/no-over/" class="tcb-button-link" target="_blank"> <span class="tcb-button-texts"><span class="tcb-button-text thrv-inline-text" data-css="tve-u-175e66a53209a87" style="">Download here</span></span> </a></div></div></div></div></div></div></div></div></div></div></div></div>
<hr />
</div>
</article>
<div class="thrv_wrapper thrv_button_shortcode tve_leftBtn blog" data-tve-style="1">
<div class="tve_btn tve_nb tve_bigBtn tve_red tve_btn1">
<a href="https://ducttapemarketing.com/blog/" class="tve_btnLink ttfm3">
<span class="tve_btn_txt">&lt; All Articles</span>
</a>
</div>
</div>
<div class="thrv_wrapper thrv_page_section" data-tve-style="1" id="full-width">
<div class="out" style="background-color: #FFF">
<div class="in darkSec" style="padding: 50px 0px 25px !important;">
<div class="thrv_wrapper thrv_post_grid related">
<div class="tve_post_grid_wrapper tve_clearfix tve_post_grid_grid">
<div class="tve_pg_row tve_clearfix">
<div class="tve_post tve_post_width_2 tve_first rocket-lazyload lazyloaded" style="height: 100%; background-image: url(&quot;https://149350408.v2.pressablecdn.com/wp-content/uploads/2020/07/domenico-loia-hGV2TfOh0ns-unsplash-400x200.jpg&quot;);" data-ll-status="loaded">
<div class="tve_pg_container">
<a href="https://ducttapemarketing.com/how-to-put-your-website-at-the-center-of-all-your-marketing/">
<div class="tve_post_grid_image_wrapper rocket-lazyload lazyloaded" style="height: 200px; background-image: url(&quot;https://149350408.v2.pressablecdn.com/wp-content/uploads/2020/07/domenico-loia-hGV2TfOh0ns-unsplash-400x200.jpg&quot;);" data-ll-status="loaded">
<div class="tve_pg_img_overlay"></div>
</div>
</a>
<span class="tve-post-grid-title " style=""><a href="https://ducttapemarketing.com/how-to-put-your-website-at-the-center-of-all-your-marketing/" title="Read more">How to Put Your Website At the Center of All Your Marketing</a></span>
<div class="tve-post-grid-text" style="border-top-width: 1px;">Your website is the heart of your online marketing presence. It’s the one place on the internet over which you have full control of visuals, messaging, and content. Everything else that you do online should drive visitors to this website. But that’s just it, there are a lot of other online channels to consider, from […]</div>
<div class="tve_pg_more"><a href="https://ducttapemarketing.com/how-to-put-your-website-at-the-center-of-all-your-marketing/" title="Read more">Read More</a> <span class="thrv-icon thrv-icon-uniE602"></span> </div>
</div>
</div>
<div class="tve_post tve_post_width_2 1 rocket-lazyload lazyloaded" style="height: 100%; background-image: url(&quot;https://149350408.v2.pressablecdn.com/wp-content/uploads/2019/01/fancycrave-277756-unsplash-400x200.jpg&quot;);" data-ll-status="loaded">
<div class="tve_pg_container">
<a href="https://ducttapemarketing.com/total-online-presence-elements/">
<div class="tve_post_grid_image_wrapper rocket-lazyload lazyloaded" style="height: 200px; background-image: url(&quot;https://149350408.v2.pressablecdn.com/wp-content/uploads/2019/01/fancycrave-277756-unsplash-400x200.jpg&quot;);" data-ll-status="loaded">
<div class="tve_pg_img_overlay"></div>
</div>
</a>
<span class="tve-post-grid-title " style=""><a href="https://ducttapemarketing.com/total-online-presence-elements/" title="Read more">The Three Elements of an Effective Total Online Presence</a></span>
<div class="tve-post-grid-text" style="border-top-width: 1px;">Marketing Podcast with John Jantsch on Total Online Presence Business owners today understand that being visible online is important. But what does having an online presence really mean? It’s a lot bigger than just having a website and a Facebook page. And when you look at the statistics on how consumers behave online, it’s easy […]</div>
<div class="tve_pg_more"><a href="https://ducttapemarketing.com/total-online-presence-elements/" title="Read more">Read More</a> <span class="thrv-icon thrv-icon-uniE602"></span> </div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div><div class="clearfix"></div>
<div class="thrv_wrapper thrv_page_section" data-tve-style="1" id="blog-cta" style="margin-top: 25px !important; margin-bottom: 0 !important;">
<div class="tve_brdr_none out" style="background-color: rgb(234, 234, 234); border-width: 0px;" data-tve-custom-colour="1044666">
<div class="in darkSec" style="padding: 50px 0px 25px !important;">
<div class="cck tve_clearfix tve_empty_dropzone">
<p class="tve_p_center ttfm2" style="margin-bottom: 10px !important;">Subscribe to the Duct Tape Marketing Podcast</p>
<p class="ttfm5 tve_p_center">If you know your small business needs marketing, but don’t have the time or resources, look no further. The Duct Tape Marketing podcast covers everything from earning referrals to managing time and being more productive.</p>
<div class="thrv_wrapper thrv_button_shortcode dtm2 tve_centerBtn" data-tve-style="1">
<div class="tve_btn tve_nb tve_bigBtn tve_red tve_btn1"> <a href="https://www.ducttapemarketing.com/category/podcast/" class="tve_btnLink" style="padding-top: 10px !important; padding-bottom: 10px !important;"> <span class="tve_left tve_btn_im"> <i></i> <span class="tve_btn_divider"></span> </span> <span class="tve_btn_txt">Listen to Podcasts</span> </a> </div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="spr"></div>
</section></div>
</div>
<footer>
<footer class="dtm-footer">
<div class="wrp clearfix"><nav class="menu-main-nav-container"><ul id="footer-nav" class="footer_menu"><li id="menu-item-47268" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a href="#">Services</a><ul class="sub-menu"> <li id="menu-item-35510" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/a-la-carte/online-audit/" class=" colch ">Online Audit</a></li>
<li id="menu-item-48805" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/a-la-carte/strategy-first/" class=" colch ">Strategy First</a></li>
<li id="menu-item-52846" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/certified-marketing-manager/" class=" colch ">Marketing Training for your team</a></li>
<li id="menu-item-49358" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/a-la-carte/website-review/" class=" colch ">Website Review</a></li>
<li id="menu-item-49359" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/how-to-build-a-local-marketing-system/" class=" colch ">Local Marketing System</a></li>
<li id="menu-item-49653" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/professional-services-marketing/" class=" colch ">Professional Services Marketing</a></li>
</ul></li>
<li id="menu-item-26608" class="heading menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a href="#">A la carte</a><ul class="sub-menu"> <li id="menu-item-26610" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/a-la-carte/seo/" class=" colch ">SEO</a></li>
<li id="menu-item-26609" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/a-la-carte/websites/" class=" colch ">Websites</a></li>
<li id="menu-item-43540" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/a-la-carte/pay-per-click/" class=" colch ">Pay Per Click</a></li>
</ul></li>
<li id="menu-item-43205" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a href="#">Guides</a><ul class="sub-menu"> <li id="menu-item-48018" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/guide-local-marketing/" class=" colch ">Local Marketing</a></li>
<li id="menu-item-48019" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/marketing-professional-services/" class=" colch ">Professional Services</a></li>
<li id="menu-item-48806" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/build-consulting-business/" class=" colch ">Consulting Business</a></li>
<li id="menu-item-49302" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/marketing-strategy-plan/" class=" colch ">Marketing Strategy Plan</a></li>
<li id="menu-item-43721" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-content-marketing/" class=" colch ">Content Marketing</a></li>
<li id="menu-item-48643" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-customer-journey/" class=" colch ">Customer Journey</a></li>
<li id="menu-item-45452" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-referrals/" class=" colch ">Referrals</a></li>
<li id="menu-item-44324" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-mobile-marketing/" class=" colch ">Mobile Marketing</a></li>
<li id="menu-item-46932" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/email-marketing-guide/" class=" colch ">Email Marketing</a></li>
<li id="menu-item-44587" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-seo/" class=" colch ">SEO</a></li>
<li id="menu-item-47073" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-paid-search/" class=" colch ">Paid Search</a></li>
<li id="menu-item-46753" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/guide-social-media/" class=" colch ">Social Media</a></li>
<li id="menu-item-45790" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-crm/" class=" colch ">CRM</a></li>
<li id="menu-item-45973" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-marketing-automation/" class=" colch ">Marketing Automation</a></li>
<li id="menu-item-45627" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/guide-website-design/" class=" colch ">Website Design</a></li>
<li id="menu-item-45409" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-advertising/" class=" colch ">Advertising</a></li>
<li id="menu-item-44847" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/guide-to-small-business-finances/" class=" colch ">Small Business Finances</a></li>
<li id="menu-item-45408" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/small-business-guide-to-sales/" class=" colch ">Sales</a></li>
</ul></li>
<li id="menu-item-26617" class="heading menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a href="#">About</a><ul class="sub-menu"> <li id="menu-item-26618" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/company/" class=" colch ">Company</a></li>
<li id="menu-item-52847" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/marketing-consultants/" class=" colch ">Consultant Network and Training</a></li>
<li id="menu-item-26619" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/hire-john-to-speak/" class=" colch ">John Jantsch Speaking</a></li>
<li id="menu-item-26620" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/books/" class=" colch ">Books</a></li>
<li id="menu-item-26621" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/blog/" class=" colch ">Blog</a></li>
<li id="menu-item-38821" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/duct-tape-marketing-podcast/" class=" colch ">Podcast</a></li>
</ul></li>
<li id="menu-item-44095" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a href="#">Contact Us</a><ul class="sub-menu"> <li id="menu-item-26622" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/about/contact-duct-tape-marketing/" class=" colch ">Contact Our Team</a></li>
<li id="menu-item-36466" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://ducttapemarketing.com/find-certified-consultant/" class=" colch ">Find A Consultant</a></li>
<li id="menu-item-46262" class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://ducttapemarketing.com/marketing-consultants/" class=" colch ">Become a Consultant</a></li>
</ul></li>
</ul></nav> <div class="fsw right">
<ul class="clearfix">
<li>
<a href="https://www.linkedin.com/in/ducttapemarketing" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://facebook.com/ducttapemarketing" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://twitter.com/ducttape" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://www.youtube.com/c/ducttapemarketingvideo" target="_blank">
<span class="awe"></span>
</a>
</li>
</ul>
<div id="copyright">
866-DUC-TAPE (382-8273)<br />Made with &lt;3 in Kansas City<br /><a href="https://www.ducttapemarketing.com/privacy-policy/" target="_blank">Privacy Policy</a> | <a href="https://ducttapemarketing.com/terms-of-service/" target="_blank">Terms of Service</a><br /><a href="https://ducttapemarketing.com/disclaimers/" target="_blank">Disclaimers</a> </div>
</div>
</div></footer> <div class="ftw">
<div class="wrp">
<div class="clear"></div>
</div>
</div>
<div class="fmn">
<div class="wrp">
<div class="fmw left">
<section class="copyright">
<div class="menu-main-nav-container"><ul id="menu-main-nav" class="footer_menu"><li id="menu-item-47268" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-47268"><a href="#">Services</a></li>
<li id="menu-item-26608" class="heading menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-26608"><a href="#">A la carte</a></li>
<li id="menu-item-43205" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-43205"><a href="#">Guides</a></li>
<li id="menu-item-26617" class="heading menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-26617"><a href="#">About</a></li>
<li id="menu-item-44095" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-44095"><a href="#">Contact Us</a></li>
</ul></div> </section>
<p class="credits">
866-DUC-TAPE (382-8273)<br />Made with &lt;3 in Kansas City<br /><a href="https://www.ducttapemarketing.com/privacy-policy/" target="_blank">Privacy Policy</a> | <a href="https://ducttapemarketing.com/terms-of-service/" target="_blank">Terms of Service</a><br /><a href="https://ducttapemarketing.com/disclaimers/" target="_blank">Disclaimers</a> </p>
</div>
<div class="fsw right">
<ul class="clearfix">
<li>
<a href="https://www.linkedin.com/in/ducttapemarketing" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://facebook.com/ducttapemarketing" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://twitter.com/ducttape" target="_blank">
<span class="awe"></span>
</a>
</li>
<li>
<a href="https://www.youtube.com/c/ducttapemarketingvideo" target="_blank">
<span class="awe"></span>
</a>
</li>
</ul>
</div>
<div class="clear"></div>
</div>
</div>
</footer>
<script src="https://cdn.loginhood.io/v2/log-cmp.js?api=93abf613-74fb-4741-aaab-a34ffb1cce18&amp;cm_id=5f64d7e011b10d001030d9c8"></script><script type="text/javascript">/**
 * Displays toast message from storage, it is used when the user is redirected after login
 */
window.onload = function () {
	let message = sessionStorage.getItem( "tcb_toast_message" );

	if ( message ) {
		tcbToast( sessionStorage.getItem( "tcb_toast_message" ), false );
		sessionStorage.removeItem( "tcb_toast_message" );
	}
};

/**
 * Displays toast message
 */
function tcbToast( message, error, callback ) {
	/* Also allow "message" objects */
	if ( typeof message !== 'string' ) {
		message = message.message || message.error || message.success;
	}
	if ( ! error ) {
		error = false;
	}

	let _icon = 'checkmark',
		_extra_class = '';
	if ( error ) {
		_icon = 'cross';
		_extra_class = ' tve-toast-error';
	}

	jQuery( 'body' ).slideDown( 'fast', function () {
		jQuery( 'body' ).prepend( '&lt;div class="tvd-toast tve-fe-message"&gt;&lt;div class="tve-toast-message"&gt;&lt;div class="tve-toast-icon-container' + _extra_class + '"&gt;&lt;span class="tve_tick thrv-svg-icon"&gt;&lt;svg xmlns="http://www.w3.org/2000/svg" class="tcb-checkmark" style="width: 100%; height: 1em; stroke-width: 0; fill: #ffffff; stroke: #ffffff;" viewBox="0 0 32 32"&gt;&lt;path d="M27 4l-15 15-7-7-5 5 12 12 20-20z"&gt;&lt;/path&gt;&lt;/svg&gt;&lt;/span&gt;&lt;/div&gt;&lt;div class="tve-toast-message-container"&gt;' + message + '&lt;/div&gt;&lt;/div&gt;&lt;/div&gt;' );
	} );

	setTimeout( function () {
		jQuery( '.tvd-toast' ).hide();

		if ( typeof callback === 'function' ) {
			callback();
		}

	}, 3000 );
}</script>
<link rel="stylesheet" href="//ducttapemarketing.com/wp-content/plugins/thrive-leads/editor-layouts/css/frontend.css?ver=2.3.7.2" data-rocket-async="style" as="style" onload="this.onload=null;this.rel='stylesheet'" type="text/css" media="all" />
<script type="text/javascript" id="thrive-main-script-js-extra">
/* &lt;![CDATA[ */
var ThriveApp = {"ajax_url":"https:\/\/ducttapemarketing.com\/wp-admin\/admin-ajax.php","lazy_load_comments":"0","comments_loaded":"0","theme_uri":"https:\/\/ducttapemarketing.com\/wp-content\/themes\/squared","translations":{"ProductDetails":"Product Details"}};
/* ]]&gt; */
</script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-content/themes/squared/js/script.min.js?ver=5.6.1" id="thrive-main-script-js"></script>
<script type="text/javascript" id="tve-dash-frontend-js-extra">
/* &lt;![CDATA[ */
var tve_dash_front = {"ajaxurl":"https:\/\/ducttapemarketing.com\/wp-admin\/admin-ajax.php","force_ajax_send":"1","is_crawler":"","recaptcha":{"api":"recaptcha","site_key":"6Lc7WQoUAAAAAPudxorUALE-3m_T2lVa0rHkcsLU","action":"tve_dash_api_handle_save"}};
/* ]]&gt; */
</script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-content/plugins/thrive-visual-editor/thrive-dashboard/js/dist/frontend.min.js?ver=2.3.7" id="tve-dash-frontend-js"></script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-content/themes/dtmc-theme/assets/js/conditionizr.min.js?ver=4.4" id="conditionizr-js"></script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-content/themes/dtmc-theme/assets/js/scripts.js?ver=1.1009" id="theme-settings-js"></script>
<script type="text/javascript" id="tve_frontend-js-extra">
/* &lt;![CDATA[ */
var tve_frontend_options = {"ajaxurl":"https:\/\/ducttapemarketing.com\/wp-admin\/admin-ajax.php","is_editor_page":"","page_events":[],"is_single":"1","social_fb_app_id":"","dash_url":"https:\/\/ducttapemarketing.com\/wp-content\/plugins\/thrive-visual-editor\/thrive-dashboard","translations":{"Copy":"Copy"}};
/* ]]&gt; */
</script>
<script type="text/javascript" src="https://149350408.v2.pressablecdn.com/wp-content/plugins/thrive-visual-editor/editor/js/dist/thrive_content_builder_frontend.min.js?ver=2.6.5.2" id="tve_frontend-js"></script>
<script type="text/javascript" src="//ducttapemarketing.com/wp-content/plugins/thrive-leads/js/frontend.min.js?ver=2.3.7.2" id="tve_leads_frontend-js"></script>
<script type="text/javascript">var tcb_post_lists=JSON.parse('[]');</script><script type="text/javascript">/*&lt;![CDATA[*/if ( !window.TL_Const ) {var TL_Const={"security":"82067dbe6c","ajax_url":"https:\/\/ducttapemarketing.com\/wp-admin\/admin-ajax.php","forms":{"shortcode_47490":{"_key":"12","form_name":"tsre on blog","trigger":"page_load","trigger_config":{},"form_type_id":"47490","main_group_id":"47490","main_group_name":"TSRE on blog","active_test_id":""}},"action_conversion":"tve_leads_ajax_conversion","action_impression":"tve_leads_ajax_impression","ajax_load":0,"main_group_id":35150,"display_options":{"allowed_post_types":[],"flag_url_match":false},"shortcode_ids":["47490"],"custom_post_data":{"http_referrer":"https:\/\/www.groovehq.com\/"},"current_screen":{"screen_type":4,"screen_id":40868},"ignored_fields":["email","_captcha_size","_captcha_theme","_captcha_type","_submit_option","_use_captcha","g-recaptcha-response","__tcb_lg_fc","__tcb_lg_msg","_state","_form_type","_error_message_option","_back_url","_submit_option","url","_asset_group","_asset_option","mailchimp_optin","tcb_token","tve_labels","tve_mapping","_api_custom_fields","_sendParams","_autofill"]};} else {ThriveGlobal.j.extend(true, TL_Const, {"security":"82067dbe6c","ajax_url":"https:\/\/ducttapemarketing.com\/wp-admin\/admin-ajax.php","forms":{"shortcode_47490":{"_key":"12","form_name":"tsre on blog","trigger":"page_load","trigger_config":{},"form_type_id":"47490","main_group_id":"47490","main_group_name":"TSRE on blog","active_test_id":""}},"action_conversion":"tve_leads_ajax_conversion","action_impression":"tve_leads_ajax_impression","ajax_load":0,"main_group_id":35150,"display_options":{"allowed_post_types":[],"flag_url_match":false},"shortcode_ids":["47490"],"custom_post_data":{"http_referrer":"https:\/\/www.groovehq.com\/"},"current_screen":{"screen_type":4,"screen_id":40868},"ignored_fields":["email","_captcha_size","_captcha_theme","_captcha_type","_submit_option","_use_captcha","g-recaptcha-response","__tcb_lg_fc","__tcb_lg_msg","_state","_form_type","_error_message_option","_back_url","_submit_option","url","_asset_group","_asset_option","mailchimp_optin","tcb_token","tve_labels","tve_mapping","_api_custom_fields","_sendParams","_autofill"]})} /*]]&gt; */</script><script type="text/javascript">var TL_Front = TL_Front || {}; TL_Front.impressions_data = TL_Front.impressions_data || {};TL_Front.impressions_data.shortcode_47490 = {"group_id":"47490","form_type_id":"47490","variation_key":"12","active_test_id":null,"output_js":true};</script><script type="text/javascript">
( function ( $ ) {
	$( function () {
		var event_data = {"form_id":"tve-leads-track-shortcode_47490","form_type":"shortcode"};
		event_data.source = 'page_load';
		setTimeout( function () {
			if ( window.TL_Front ) {
				ThriveGlobal.j( TL_Front ).trigger( 'showform.thriveleads', event_data );
			}
		}, 200 );
	} );
})( ThriveGlobal.j );
</script>
<script type="text/javascript">
			(function() {
			var t   = document.createElement( 'script' );
			t.type  = 'text/javascript';
			t.async = true;
			t.id    = 'gauges-tracker';
			t.setAttribute( 'data-site-id', '5c014d80701bf42a5ee6453d' );
			t.src = '//secure.gaug.es/track.js';
			var s = document.getElementsByTagName( 'script' )[0];
			s.parentNode.insertBefore( t, s );
			})();
		</script>
<script>window.lazyLoadOptions={elements_selector:"img[data-lazy-src],.rocket-lazyload,iframe[data-lazy-src]",data_src:"lazy-src",data_srcset:"lazy-srcset",data_sizes:"lazy-sizes",class_loading:"lazyloading",class_loaded:"lazyloaded",threshold:300,callback_loaded:function(element){if(element.tagName==="IFRAME"&amp;&amp;element.dataset.rocketLazyload=="fitvidscompatible"){if(element.classList.contains("lazyloaded")){if(typeof window.jQuery!="undefined"){if(jQuery.fn.fitVids){jQuery(element).parent().fitVids()}}}}}};window.addEventListener('LazyLoad::Initialized',function(e){var lazyLoadInstance=e.detail.instance;if(window.MutationObserver){var observer=new MutationObserver(function(mutations){var image_count=0;var iframe_count=0;var rocketlazy_count=0;mutations.forEach(function(mutation){for(i=0;i&lt;mutation.addedNodes.length;i++){if(typeof mutation.addedNodes[i].getElementsByTagName!=='function'){continue}
if(typeof mutation.addedNodes[i].getElementsByClassName!=='function'){continue}
images=mutation.addedNodes[i].getElementsByTagName('img');is_image=mutation.addedNodes[i].tagName=="IMG";iframes=mutation.addedNodes[i].getElementsByTagName('iframe');is_iframe=mutation.addedNodes[i].tagName=="IFRAME";rocket_lazy=mutation.addedNodes[i].getElementsByClassName('rocket-lazyload');image_count+=images.length;iframe_count+=iframes.length;rocketlazy_count+=rocket_lazy.length;if(is_image){image_count+=1}
if(is_iframe){iframe_count+=1}}});if(image_count&gt;0||iframe_count&gt;0||rocketlazy_count&gt;0){lazyLoadInstance.update()}});var b=document.getElementsByTagName("body")[0];var config={childList:!0,subtree:!0};observer.observe(b,config)}},!1)</script><script data-no-minify="1" async="" src="https://149350408.v2.pressablecdn.com/wp-content/plugins/wp-rocket/assets/js/lazyload/16.1/lazyload.min.js"></script><script>function lazyLoadThumb(e){var t='&lt;img loading="lazy" data-lazy-src="https://i.ytimg.com/vi/ID/hqdefault.jpg" alt="" width="480" height="360"&gt;&lt;noscript&gt;&lt;img src="https://i.ytimg.com/vi/ID/hqdefault.jpg" alt="" width="480" height="360"&gt;&lt;/noscript&gt;',a='&lt;div class="play"&gt;&lt;/div&gt;';return t.replace("ID",e)+a}function lazyLoadYoutubeIframe(){var e=document.createElement("iframe"),t="ID?autoplay=1";t+=0===this.dataset.query.length?'':'&amp;'+this.dataset.query;e.setAttribute("src",t.replace("ID",this.dataset.src)),e.setAttribute("frameborder","0"),e.setAttribute("allowfullscreen","1"),e.setAttribute("allow", "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"),this.parentNode.replaceChild(e,this)}document.addEventListener("DOMContentLoaded",function(){var e,t,a=document.getElementsByClassName("rll-youtube-player");for(t=0;t&lt;a.length;t++)e=document.createElement("div"),e.setAttribute("data-id",a[t].dataset.id),e.setAttribute("data-query", a[t].dataset.query),e.setAttribute("data-src", a[t].dataset.src),e.innerHTML=lazyLoadThumb(a[t].dataset.id),e.onclick=lazyLoadYoutubeIframe,a[t].appendChild(e)});</script><script>"use strict";var wprRemoveCPCSS=function wprRemoveCPCSS(){var elem;document.querySelector('link[data-rocket-async="style"][rel="preload"]')?setTimeout(wprRemoveCPCSS,200):(elem=document.getElementById("rocket-critical-css"))&amp;&amp;"remove"in elem&amp;&amp;elem.remove()};window.addEventListener?window.addEventListener("load",wprRemoveCPCSS):window.attachEvent&amp;&amp;window.attachEvent("onload",wprRemoveCPCSS);</script><noscript>&lt;link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Karla%3A400%2C700%7CMerriweather%3A400%2C700%7CLora%3A400%2C400italic%2C700italic%2C700%7CMerriweather%3A400%2C400italic%2C700&amp;#038;display=swap" /&gt;&lt;link rel="stylesheet" href="https://ducttapemarketing.com/wp-content/cache/min/1/eed7c9990254e0603658471d5eac0b86.css" media="all" data-minify="1" /&gt;&lt;link rel='stylesheet' id='tve_leads_forms-css'  href='//ducttapemarketing.com/wp-content/plugins/thrive-leads/editor-layouts/css/frontend.css?ver=2.3.7.2' type='text/css' media='all' /&gt;</noscript>

<article id="comments">
<div class="awr">
<div class="cmb" style="margin-left: 0px;" id="thrive_container_list_comments">
</div>
</div>
</article>
<div id="comment-bottom"></div>


<div><iframe style="position: fixed; inset: 0px; width: 100%; height: 100%; display: none; z-index: 999999; border: none;"></iframe></div></body></html>
FakeContent;
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnails');
        });
        $response = $this->client('POST', 'card', ['url' => $firstUrl, 'rawHtml' => $fakeContent]);

        $cardId = $response->json()['data']['id'];
        $card = Card::find($cardId);

        Queue::assertPushed(GetTags::class, 1);
        $this->assertDatabaseHas('cards', [
            'content'     => $card->content,
            'description' => $card->description,
            'title'       => $card->title,
            'properties'  => json_encode(['should_sync' => false], JSON_THROW_ON_ERROR),
        ]);
    }

    public function testWithNoTags(): void
    {
        Queue::fake();

        $this->client('POST', 'card', [
            'url'      => 'https://fail.com',
            'content'  => 'cool content',
            'withTags' => false,
        ]);

        Queue::assertPushed(GetTags::class, 0);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardNullFields(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();
        $original = [
            'url'         => 'https://asdasd.com',
            'title'       => 'old title',
            'content'     => 'good content',
            'description' => 'awesome description',
        ];

        $response = $this->client('POST', 'card', $original);

        $update = [
            'title'       => 'new title',
            'description' => null,
        ];
        $this->client('PATCH', 'card/'.$this->getResponseData($response)->get('id'), $update);
        $this->assertDatabaseHas('cards', [
            'url'         => $original['url'],
            'title'       => $update['title'],
            'content'     => $original['content'],
            'description' => null,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardNotFound(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('PATCH', 'card/12000');
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardForbidden(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('PATCH', 'card/5');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardNotFound(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('DELETE', 'card/12000');
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardForbidden(): void
    {
        $this->mock(ExtractDataHelper::class);
        $response = $this->client('DELETE', 'card/5');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardSuccess(): void
    {
        $this->mock(ExtractDataHelper::class);
        Queue::fake();
        Storage::fake();
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $this->assertDatabaseHas('cards', [
            'id' => $cardId,
        ]);
        $response = $this->client('DELETE', 'card/'.$cardId);
        self::assertEquals(204, $response->getStatusCode());
        $this->assertDatabaseMissing('cards', [
            'id' => $cardId,
        ]);
    }
}
