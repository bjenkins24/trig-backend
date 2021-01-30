<?php

namespace App\Modules\CardTag;

use App\Models\Card;
use App\Models\CardTag;
use App\Models\Tag;
use App\Modules\Tag\TagRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CardTagRepository
{
    private TagRepository $tagRepository;

    public function __construct(TagRepository $tagRepository)
    {
        $this->tagRepository = $tagRepository;
    }

    /**
     * @throws Throwable
     */
    public function replaceTags(Card $card, array $tags, array $hypernyms): Card
    {
        DB::transaction(function () use ($tags, $hypernyms, $card) {
            $cardTags = $card->cardTags();

            $cardTags->get()->each(static function ($cardTag) {
                $tag = Tag::where('id', $cardTag->tag_id)->first();

                $cardTag->delete();
                $tagExistsOnCard = CardTag::where('tag_id', $tag->id)->exists();
                if (! $tagExistsOnCard) {
                    $tag->delete();
                }
            });

            $workspaceId = $card->workspace_id;
            foreach ($tags as $tagKey => $tagString) {
                if (! $tagString) {
                    continue;
                }
                $tag = $this->tagRepository->findSimilar($tagString, $workspaceId);
                if (! $tag) {
                    // Add a tag
                    $tag = Tag::create([
                        'workspace_id'    => $workspaceId,
                        'tag'             => $tagString,
                        'hypernym'        => $hypernyms[$tagKey] ?? null,
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
