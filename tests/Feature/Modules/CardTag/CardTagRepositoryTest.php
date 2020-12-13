<?php

namespace Tests\Feature\Modules\CardTagRepository;

use App\Models\Card;
use App\Models\Tag;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardExists;
use App\Modules\Card\Exceptions\CardOrganizationIdMustExist;
use App\Modules\Card\Exceptions\CardUserIdMustExist;
use App\Modules\CardTag\CardTagRepository;
use Tests\TestCase;
use Throwable;

class CardTagRepositoryTest extends TestCase
{
    /**
     * @throws CardExists
     * @throws CardOrganizationIdMustExist
     * @throws CardUserIdMustExist
     * @throws Throwable
     */
    public function testReplaceTags()
    {
        $this->refreshDb();
        $cardId = 1;
        $card = Card::find($cardId);
        $firstSetTags = ['cool tag', 'cool tag 2', 'cool tag 3'];
        app(CardTagRepository::class)->replaceTags($card, $firstSetTags);

        $card2 = app(CardRepository::class)->updateOrInsert([
            'organization_id' => $card->organization_id,
            'user_id'         => $card->user_id,
            'title'           => 'cool title',
            'url'             => 'hello',
            'card_type_id'    => 1,
        ]);

        // There are two cool tag 2's so we won't be deleting it later
        app(CardTagRepository::class)->replaceTags($card2, [$firstSetTags[1]]);
        foreach ($firstSetTags as $tag) {
            $this->assertDatabaseHas('tags', [
                'organization_id' => $card->organization_id,
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

        app(CardTagRepository::class)->replaceTags($card, $secondSetTags);

        $this->assertDatabaseMissing('tags', [
            'organization_id' => $card->organization_id,
            'tag'             => $firstSetTags[0],
        ]);

        $this->assertDatabaseHas('tags', [
            'organization_id' => $card->organization_id,
            'tag'             => $firstSetTags[1],
        ]);

        $this->assertDatabaseMissing('card_tags', [
            'card_id'    => $cardId,
            'tag_id'     => $firstTagId,
        ]);

        $this->assertDatabaseMissing('card_tags', [
            'organization_id'    => $cardId,
            'tag_id'             => $secondTagId,
        ]);

        $this->refreshDb();
    }
}
