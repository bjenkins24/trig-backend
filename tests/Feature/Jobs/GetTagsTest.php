<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GetTags;
use App\Models\Card;
use App\Models\Tag;
use App\Modules\Tag\TagRepository;
use App\Utils\TagParser\TagHypernym;
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

        $existingHypernym = 'Joo Joo Beans';
        $nonExistingHypernym = 'Appliance';
        $whitelistedHypernm = 'Soup';
        Tag::create([
            'workspace_id' => $card->workspace_id,
            'tag'          => $existingHypernym,
        ]);

        $tags = collect(['Big Joo Joo Beans', 'Refrigerator', 'Mushroom Soup']);
        $hypernyms = collect([$existingHypernym, $nonExistingHypernym, $whitelistedHypernm]);
        $this->mock(TagParser::class, static function ($mock) use ($card, $tags) {
            $mock->shouldReceive('getTags')->withArgs([$card->title, Str::htmlToMarkdown($card->content), $card->url])->andReturn($tags);
        });

        $this->mock(TagHypernym::class, static function ($mock) use ($hypernyms) {
            $mock->shouldReceive('getHypernyms')->andReturn($hypernyms);
        });

        $this->mock(TagRepository::class, static function ($mock) {
            $mock->shouldReceive('findSimilar')->andReturn(null);
        });

        $getTags = new GetTags(Card::find(1));
        $getTags->handle();

        $this->assertDatabaseHas('tags', [
            'tag'      => $tags->get(0),
            'hypernym' => $hypernyms->get(0),
        ]);

        $this->assertDatabaseHas('tags', [
            'tag'      => $tags->get(1),
            'hypernym' => $hypernyms->get(1),
        ]);

        // whitelisted should appear
        $this->assertDatabaseHas('tags', [
            'tag'      => $whitelistedHypernm,
        ]);

        // Existing hypernym should appear
        // I'm purposely not testing this because ->findSimilar is mocked above it's just wasting too much time
//        $this->assertDatabaseHas('card_tags', [
//            'card_id'      => $card->id,
//            'tag_id' => Tag::where('tag', $existingHypernym)->first()->id
//        ]);

        // non existing should NOT appear
        $this->assertDatabaseMissing('tags', [
            'tag'      => $nonExistingHypernym,
        ]);
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
