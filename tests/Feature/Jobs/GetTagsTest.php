<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GetTags;
use App\Models\Card;
use App\Modules\Tag\TagRepository;
use App\Utils\TagParser\TagParser;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class GetTagsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Throwable
     */
    public function testGetTags(): void
    {
        $card = Card::find(1);
        $this->mock(TagParser::class, static function ($mock) use ($card) {
            $tags = collect(['Tag', 'Cool Tag']);
            $mock->shouldReceive('getTags')->withArgs([$card->title, Str::htmlToMarkdown($card->content)])->andReturn($tags);
            $mock->shouldReceive('replaceTags')->withArgs([$card, $tags->toArray()]);
        });

        $this->mock(TagRepository::class, static function ($mock) {
            $mock->shouldReceive('findSimilar')->andReturn(null);
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
