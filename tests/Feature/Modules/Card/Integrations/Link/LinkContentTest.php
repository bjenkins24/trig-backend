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
        $cardData = app(LinkContent::class)->getCardContentData(Card::find(1));
        self::assertEquals(collect([
            'title'       => $mockWebsite->get('title'),
            'content'     => $mockWebsite->get('text'),
            'author'      => $mockWebsite->get('author'),
            'description' => $mockWebsite->get('excerpt'),
            'image'       => $mockWebsite->get('image'),
        ]), $cardData);
    }
}
