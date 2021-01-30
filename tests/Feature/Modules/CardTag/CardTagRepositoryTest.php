<?php

namespace Tests\Feature\Modules\CardTag;

use App\Models\Card;
use App\Models\CardTag;
use App\Models\Tag;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardExists;
use App\Modules\Card\Exceptions\CardUserIdMustExist;
use App\Modules\Card\Exceptions\CardWorkspaceIdMustExist;
use App\Modules\CardTag\CardTagRepository;
use App\Modules\Tag\TagRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class CardTagRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws CardExists
     * @throws CardWorkspaceIdMustExist
     * @throws CardUserIdMustExist
     * @throws Throwable
     */
    public function testReplaceTags(): void
    {
        $cardId = 1;
        $card = Card::find($cardId);
        $firstSetTags = ['cool tag', 'cool tag 2', 'cool tag 3', ''];
        $this->mock(TagRepository::class, static function ($mock) {
            $mock->shouldReceive('findSimilar');
        });

        app(CardTagRepository::class)->replaceTags($card, $firstSetTags, []);

        $card2 = app(CardRepository::class)->updateOrInsert([
            'workspace_id'    => $card->workspace_id,
            'user_id'         => $card->user_id,
            'title'           => 'cool title',
            'url'             => 'hello',
            'card_type_id'    => 1,
        ]);

        // There are two cool tag 2's so we won't be deleting it later
        app(CardTagRepository::class)->replaceTags($card2, [$firstSetTags[1]], []);
        foreach ($firstSetTags as $tag) {
            if (! $tag) {
                continue;
            }
            $this->assertDatabaseHas('tags', [
                'workspace_id'    => $card->workspace_id,
                'tag'             => $tag,
            ]);
            $tagModel = Tag::where('tag', $tag)->first();
            $this->assertDatabaseHas('card_tags', [
                'card_id'    => $cardId,
                'tag_id'     => $tagModel->id,
            ]);
        }

        // We want this tag to be the _only_ tag on this card after this run
        $secondSetTags = ['only 1 tag'];
        $firstTagId = Tag::where('tag', $firstSetTags[0])->first()->id;
        $secondTagId = Tag::where('tag', $firstSetTags[1])->first()->id;

        app(CardTagRepository::class)->replaceTags($card, $secondSetTags, []);

        $this->assertDatabaseMissing('tags', [
            'workspace_id'    => $card->workspace_id,
            'tag'             => $firstSetTags[0],
        ]);

        $this->assertDatabaseHas('tags', [
            'workspace_id'    => $card->workspace_id,
            'tag'             => $firstSetTags[1],
        ]);

        $this->assertDatabaseMissing('card_tags', [
            'card_id'    => $cardId,
            'tag_id'     => $firstTagId,
        ]);

        $this->assertDatabaseMissing('card_tags', [
            'workspace_id'       => $cardId,
            'tag_id'             => $secondTagId,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testDenormalizeTags(): void
    {
        $card = Card::find(1);
        $firstSetTags = ['cool tag', 'cool tag 2', 'cool tag 3'];
        $this->mock(TagRepository::class, static function ($mock) {
            $mock->shouldReceive('findSimilar');
        });
        app(CardTagRepository::class)->replaceTags($card, $firstSetTags, []);
        $denormalized = app(CardTagRepository::class)->denormalizeTags($card);

        self::assertEquals(collect($firstSetTags), $denormalized);
    }

    /**
     * @group n
     */
    public function testAddHypernymsToOldCardsTest(): void
    {
        $card = Card::find(1);
        $tag = Tag::create([
            'tag'          => 'Refrigerator',
            'hypernym'     => 'Appliance',
            'workspace_id' => $card->workspace_id,
        ]);
        CardTag::create([
            'card_id' => $card->id,
            'tag_id'  => $tag->id,
        ]);
        $this->assertDatabaseMissing('tags', [
            'tag'          => 'Appliance',
            'workspace_id' => $card->workspace_id,
        ]);
        app(CardTagRepository::class)->addHypernymsToOldCards(collect(['Appliance', 'Furniture']), $card->workspace_id);
        $this->assertDatabaseHas('tags', [
            'tag'          => 'Appliance',
            'workspace_id' => $card->workspace_id,
        ]);
        $this->assertDatabaseHas('card_tags', [
            'card_id' => $card->id,
            'tag_id'  => Tag::where('tag', 'Appliance')->first()->id,
        ]);
    }
}
