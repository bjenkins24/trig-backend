<?php

namespace App\Modules\CardTag;

use App\Models\Card;
use App\Models\CardTag;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CardTagRepository
{
    /**
     * @throws Throwable
     */
    public function replaceTags(Card $card, array $tags): Card
    {
        DB::transaction(static function () use ($tags, $card) {
            $cardTags = $card->cardTags();

            $cardTags->get()->each(static function ($cardTag) {
                $tag = Tag::where('id', $cardTag->tag_id)->first();

                $cardTag->delete();
                $tagExistsOnCard = CardTag::where('tag_id', $tag->id)->exists();
                if (! $tagExistsOnCard) {
                    $tag->delete();
                }
            });

            $organizationId = $card->organization_id;
            foreach ($tags as $tagString) {
                if (! $tagString) {
                    continue;
                }
                $tag = Tag::where('tag', $tagString)->where('organization_id', $organizationId)->first();
                if (! $tag) {
                    // Add a card_tag
                    $tag = Tag::create([
                        'organization_id' => $card->organization_id,
                        'tag'             => $tagString,
                    ]);
                }
                $cardTagExists = CardTag::where('card_id', $card->id)->where('tag_id', $tag->id)->exists();
                if (! $cardTagExists) {
                    CardTag::create([
                        'card_id' => $card->id,
                        'tag_id'  => $tag->id,
                    ]);
                }
            }
        });

        return $card;
    }

    public function denormalizeTags(Card $card): Collection
    {
        $cardTags = $card->cardTags()->get();

        return $cardTags->reduce(static function ($carry, $cardTag) {
            $carry->push($cardTag->tag()->first()->tag);

            return $carry;
        }, collect([]));
    }
}
