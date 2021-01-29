<?php

namespace Tests\Feature\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Integrations\Link\LinkContent;
use App\Utils\TikaWebClientWrapper;
use App\Utils\WebsiteExtraction\WebsiteFactory;
use App\Utils\WebsiteExtraction\WebsiteTypes\GenericExtraction;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Exception
     */
    public function testGetCardContentDataFail(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->mock(GenericExtraction::class, static function ($mock) {
            $mock->shouldReceive('getWebsite')->andThrow(new Exception('Yes!'));
            $mock->shouldReceive('setUrl');
        });
        $card = Card::find(1);
        $card->url = 'https://www.productplan.com/glossary/feature-less-roadmap/';
        $cardData = app(LinkContent::class)->getCardContentData($card);

        self::assertEmpty($cardData);
        $this->assertDatabaseHas('card_syncs', [
            'card_id' => $card->id,
            'status'  => 0,
        ]);
    }

    /**
     * @throws Exception
     */
    public function testGetCardDataContent(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $mockWebsite = app(WebsiteFactory::class)
            ->make('cool')
            ->setContent('cool 2')
            ->setTitle('hello')
            ->setAuthor('my author')
            ->setExcerpt('description')
            ->setImage('cool image')
            ->setScreenshot('cool screenshot');

        $this->mock(GenericExtraction::class, static function ($mock) use ($mockWebsite) {
            $mock->shouldReceive('getWebsite')->andReturn($mockWebsite);
            $mock->shouldReceive('setUrl');
        });
        $card = Card::find(1);
        $card->url = 'https://www.productplan.com/glossary/feature-less-roadmap/';
        $cardData = app(LinkContent::class)->getCardContentData($card);

        self::assertEquals(collect([
            'title'       => $mockWebsite->getTitle(),
            'content'     => $mockWebsite->getContent(),
            'author'      => $mockWebsite->getAuthor(),
            'description' => $mockWebsite->getExcerpt(),
            'image'       => $mockWebsite->getImage(),
            'screenshot'  => $mockWebsite->getScreenshot(),
        ]), $cardData);
    }
}
