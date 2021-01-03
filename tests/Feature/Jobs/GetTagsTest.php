<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GetTags;
use App\Models\Card;
use App\Utils\TagParser\TagParser;
use Exception;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class GetTagsTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testGetTags(): void
    {
        $this->refreshDb();
        $card = Card::find(1);
        $this->mock(TagParser::class, static function ($mock) use ($card) {
            $tags = collect(['Tag', 'Cool Tag']);
            $mock->shouldReceive('getTags')->withArgs([$card->title, Str::htmlToMarkdown($card->content)])->andReturn($tags);
            $mock->shouldReceive('replaceTags')->withArgs([$card, $tags->toArray()]);
        });

        $getTags = new GetTags(Card::find(1));
        $getTags->handle();
    }

    /**
     * @throws Throwable
     */
    public function testGetTagsFail(): void
    {
        $this->mock(TagParser::class, static function ($mock) {
            $mock->shouldReceive('getTags')->andThrow(new Exception('oops!'));
        });
        $getTags = new GetTags(Card::find(1));
        self::assertFalse($getTags->handle());
    }
}
