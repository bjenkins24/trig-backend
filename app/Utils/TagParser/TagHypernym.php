<?php

namespace App\Utils\TagParser;

use Illuminate\Support\Collection;

class TagHypernym
{
    // Any strings in this array that appear as a hypernym will tell Trig to use a better GPT3 engine
    // every string in this array must be ALL lowercase
    private const BAD_HYPERNYMS = [
        'white',
    ];

    // These hypernyms will become tags right away
    public const WHITELISTED_HYPERNYMS = [
        'space', 'soup', 'salad', 'racism', 'anime',
    ];

    private TagUtils $tagUtils;
    private TagHeuristics $tagHeuristics;
    private TagManualAdditions $tagManualAdditions;
    private TagPrompts $tagPrompts;

    public function __construct(
        TagUtils $tagUtils,
        TagHeuristics $tagHeuristics,
        TagManualAdditions $tagManualAdditions,
        TagPrompts $tagPrompts
    ) {
        $this->tagUtils = $tagUtils;
        $this->tagHeuristics = $tagHeuristics;
        $this->tagManualAdditions = $tagManualAdditions;
        $this->tagPrompts = $tagPrompts;
    }

    private function getHypernym(string $tag, int $engineId = 1): string
    {
        $response = $this->tagPrompts->completeHypernym($tag, $engineId);
        if (! isset($response['choices'][0]['text']) || null === $response) {
            return '';
        }

        $completion = $response['choices'][0]['text'];

        return $this->tagUtils->completionToArray($completion)[0];
    }

    public function getHypernyms(array $tags, int $engineId = 1): Collection
    {
        $cleanedTags = $this->tagHeuristics->removeHeuristicTags(
            $this->tagManualAdditions->removeHighLevelTags($tags)
        );
        $hypernyms = [];
        $count = 0;
        foreach ($cleanedTags as $tagKey => $tag) {
            // If we removed the tag we want the hypernym to just be an empty string
            if ($tagKey !== $count) {
                $hypernyms[$tagKey - 1] = '';
                ++$count;
            }
            $hypernym = $this->getHypernym($tag, $engineId);
            // If the hypernym didn't change let's try up an engine if it still doesn't change
            // we're gonna stop trying
            if (in_array(strtolower($hypernym), self::BAD_HYPERNYMS, true) || strtolower($hypernym) === strtolower($tag)) {
                $hypernym = $this->getHypernym($tag, $engineId + 1);
            }
            $hypernyms[$tagKey] = $hypernym;
            ++$count;
        }

        return collect($hypernyms);
    }
}
