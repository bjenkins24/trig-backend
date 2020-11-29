<?php

namespace Tests\Feature\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Integrations\Link\LinkContent;
use App\Utils\WebsiteExtraction\WebsiteTypes\GenericExtraction;
use Exception;
use Tests\TestCase;

class LinkContentTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGetCardContentDataFail(): void
    {
        $this->refreshDb();
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
        $this->refreshDb();
        $mockWebsite = collect([
            'title'       => 'hello',
            'text'        => 'cool text',
            'author'      => 'my author',
            'excerpt'     => 'descipriton',
            'image'       => 'cool image',
        ]);
        $this->mock(GenericExtraction::class, static function ($mock) use ($mockWebsite) {
            $mock->shouldReceive('getWebsite')->andReturn($mockWebsite);
            $mock->shouldReceive('setUrl');
        });
        $card = Card::find(1);
        $card->url = 'https://www.productplan.com/glossary/feature-less-roadmap/';
        $cardData = app(LinkContent::class)->getCardContentData($card);

        self::assertEquals(collect([
            'title'       => $mockWebsite->get('title'),
            'content'     => $mockWebsite->get('html'),
            'author'      => $mockWebsite->get('author'),
            'description' => $mockWebsite->get('excerpt'),
            'image'       => $mockWebsite->get('image'),
        ]), $cardData);
    }
}
