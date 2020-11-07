<?php

namespace Tests\Feature\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Integrations\Link\LinkContent;
use App\Utils\ExtractDataHelper;
use Tests\TestCase;

class LinkContentTest extends TestCase
{
    public function testGetCardDataContent(): void
    {
        $mockWebsite = collect([
            'title'       => 'hello',
            'text'        => 'cool text',
            'author'      => 'my author',
            'excerpt'     => 'descipriton',
            'image'       => 'cool image',
        ]);
        $this->mock(ExtractDataHelper::class, static function ($mock) use ($mockWebsite) {
            $mock->shouldReceive('getWebsite')->andReturn($mockWebsite);
        });
        $card = Card::find(1);
        $card->url = 'https://www.productplan.com/glossary/feature-less-roadmap/';
        $cardData = app(LinkContent::class)->getCardContentData($card);

        self::assertEquals(collect([
            'title'       => $mockWebsite->get('title'),
            'content'     => $mockWebsite->get('text'),
            'author'      => $mockWebsite->get('author'),
            'description' => $mockWebsite->get('excerpt'),
            'image'       => $mockWebsite->get('image'),
        ]), $cardData);
    }
}
