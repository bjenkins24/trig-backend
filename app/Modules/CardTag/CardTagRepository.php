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
    public function replaceTags(Card $card, array $tags, array $hypernyms): ?Card
    {
        // The card could have been deleted BEFORE this finishes. In that case we don't want to save the card data
        if (! Card::where(['id' => $card->id])->exists()) {
            return null;
        }
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
                if ($tag && $tag->workspace_id !== $workspaceId) {
                    continue;
                }
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

    public function addHypernymsToOldCards(Collection $tags, int $workspaceId): void
    {
        $tags->each(function ($tag) use ($workspaceId) {
            $tagsWithHypernyms = Tag::where(['hypernym' => $tag, 'workspace_id' => $workspaceId])->get();
            $tagIds = [];
            foreach ($tagsWithHypernyms as $hyponymTag) {
                $tagIds[] = $hyponymTag->id;
            }
            if (empty($tagIds)) {
                return;
            }

            // Cards that have this tag as a hypernym
            $cardTags = CardTag::whereIn('tag_id', $tagIds)->get();
            if ($cardTags->isEmpty()) {
                return;
            }

            $newTag = Tag::where(['tag' => $tag, 'workspace_id' => $workspaceId])->first();
            if (! $newTag) {
                $newTag = Tag::create([
                   'tag'           => $tag,
                   'workspace_id'  => $workspaceId,
               ]);
            }
            $cardIds = collect([]);
            $cardTags->each(static function ($cardTag) use (&$cardIds) {
                $cardIds[] = $cardTag->card()->first()->id;
            });
            $cardIds->unique()->values()->each(function ($cardId) use ($tag, $newTag) {
                $tags = $this->getTags(Card::find($cardId));
                if ($tags->contains($tag)) {
                    return false;
                }

                return CardTag::create([
                    'card_id' => $cardId,
                    'tag_id'  => $newTag->id,
                ]);
            });
        });
    }

    /**
     * Please don't type the param here. It's needed untyped because of Nova.
     *
     * @param $card
     */
    public function getTags($card): Collection
    {
        $tags = collect([]);
        $card->cardTags()->each(static function ($cardTag) use (&$tags) {
            $tags->push($cardTag->tag()->first()->tag);
        });

        return $tags;
    }

    public function getHypernyms($card): Collection
    {
        $hypernyms = collect([]);
        $card->cardTags()->each(static function ($cardTag) use (&$hypernyms) {
            $hypernyms->push($cardTag->tag()->first()->hypernym);
        });

        return $hypernyms;
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
