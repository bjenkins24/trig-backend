<?php

namespace App\Utils\TagParser;

use App\Utils\Gpt3;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TagParser
{
    private Gpt3 $gpt3;
    private TagHeuristics $tagHeuristics;
    private TagManualAdditions $tagManualAdditions;
    private TagStringRemoval $tagStringRemoval;
    private TagPrompts $tagPrompts;

    public function __construct(
        Gpt3 $gpt3,
        TagHeuristics $tagHeuristics,
        TagManualAdditions $tagManualAdditions,
        TagStringRemoval $tagStringRemoval,
        TagPrompts $tagPrompts
    ) {
        $this->gpt3 = $gpt3;
        $this->tagHeuristics = $tagHeuristics;
        $this->tagManualAdditions = $tagManualAdditions;
        $this->tagStringRemoval = $tagStringRemoval;
        $this->tagPrompts = $tagPrompts;
    }

    private function completionToArray(string $completion): array
    {
        $completion = trim(preg_replace("/[\r|\n].*/", '', $completion));
        $potentialTags = explode(',', $completion);
        // It wasn't able to explode it correctly let's try spaces
        if (1 === count($potentialTags)) {
            $potentialTags = explode(' ', $completion);
        }
        $tags = [];

        foreach ($potentialTags as $tag) {
            $cleanedTag = trim($tag);
            if ($cleanedTag) {
                $tags[] = $cleanedTag;
            } else {
                break;
            }
        }

        return $tags;
    }

    private function increaseEngine(int $engineId, string $completion, string $input, string $title, string $documentText, ?string $url, string $promptType): Collection
    {
        $nextEngineNoticeMessage = '';
        if ($engineId < 3) {
            $shortInputForLog = Str::truncateOnWord($input, 200);
            $nextEngineNoticeMessage = "Trying {$this->gpt3->getEngine($engineId + 1)} engine for tag generation. Got $completion from $shortInputForLog";
        }

        Log::notice($nextEngineNoticeMessage);

        return $this->getTags($title, $documentText, $url, $promptType, $engineId + 1);
    }

    /**
     * This will take a formatted string with lists, random line breaks, or just a poorly written document and clean
     * it up to a single raw block of text.
     */
    public function docToBlock(string $rawText, int $totalCharacters = 2500): string
    {
        // Replace line breaks that don't have a `period` with a period, but only if they don't have a period preceding
        // them and they aren't followed by another line break
        $formatted = preg_replace('/(?<![?!.\r\n])[\r\n]/', '.', $rawText);
        // Truncate on a word and remove left over line breaks
        $formatted = Str::truncateOnWord(Str::removeLineBreaks($formatted), $totalCharacters);
        // Remove lists from the string.
        $formatted = preg_replace('/(\d+)\.(?=\D)/', '', $formatted);
        // Replace colons with periods
        // Also replace double spaces after periods. Babbage also does that poorly
        $formatted = str_replace([':', '.  ', '?  ', '!  '], ['.', '. ', '? ', '! '], $formatted);
        // Replace periods with no space with a period WITH a space. Babbage does _not_ do well with weird text
        $formatted = preg_replace('/([.!?])([^ !?\d])/', '$1 $2', $formatted);

        return $formatted;
    }

    private function cleanTags(array $tags, string $title, string $documentText, ?string $url): Collection
    {
        // Sometimes tags come in like D&amp;D instead of D&D
        $newTags = $tags;
        foreach ($tags as $tagKey => $tag) {
            $newTags[$tagKey] = htmlspecialchars_decode($tag);
        }

        return collect(
            array_values(
                array_unique(
                    $this->tagStringRemoval->remove(
                        $this->tagManualAdditions->addHighLevelTags(
                            $this->tagHeuristics->addHeuristicTags(
                                $newTags,
                                $title,
                                $documentText,
                                $url
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * If the promptType is 'example' it will give examples to GPT-3 which act more like keywords. GPT-3 inheritantly
     * knows what tags are. There are some times that the prompt type 'tag' may work better. But there are too many
     * inconsistencies currently. I'm keeping it here because it was difficult to get working at all, and it seems
     * like there is a lot of potential there. So we may come back and use it in the future, or test the differences.
     */
    public function getTags(string $title, string $documentText, ?string $url = '', string $promptType = 'example', int $engineId = 1): Collection
    {
        if (! $documentText || ! $title) {
            return collect([]);
        }

        $exampleTags = [];
        switch ($promptType) {
            case 'tag':
                $blockText = $this->docToBlock($documentText, 2500);
                $response = $this->tagPrompts->completeTag($blockText);
                break;
            case 'example':
            default:
                // The example prompt has more tokens by default so let's limit the characters we have for our input
                $blockText = $this->docToBlock($documentText, 1600);
                [$response, $exampleTags] = $this->tagPrompts->completeWithExamples($title, $blockText);
        }

        // If there was an error $response will come back as null - we just want to abort in that case
        if (! isset($response['choices'][0]['text']) || null === $response) {
            return collect([]);
        }

        if (is_array($response) && empty($response)) {
            if (3 !== $engineId) {
                return $this->increaseEngine($engineId, '', $blockText, $title, $documentText, $url, $promptType);
            }

            return collect([]);
        }

        $completion = $response['choices'][0]['text'];
        $tags = $this->completionToArray($completion);

        $isCounting = count($this->tagStringRemoval->removeConsecutiveNumbers($tags)) !== count($tags);
        $hasBadWords = count($this->tagStringRemoval->removeBadWords($tags)) !== count($tags);

        // If there are consecutive numbers or we words on that bad list - let's try a new engine - that means it stunk
        if (3 !== $engineId && ($isCounting || $hasBadWords)) {
            return $this->increaseEngine($engineId, $completion, $blockText, $title, $documentText, $url, $promptType);
        }

        // If the result includes the example tags then the tag retrieval didn't work. Let's try a better engine
        foreach ($tags as $tagKey => $tag) {
            // Bad results tend to have 4 words or more - let's just go to currie here as davinci will often
            // get 4 words too - and this is fairly common. Save on cost
            // If the 'example' prompt type is used, we know if it was a bad completion if they just repeated the
            // last tags. In that case we're gonna increase the engine here (only to currie)
            if ($engineId < 2 && (str_word_count($tag) > 3 || in_array($tag, $exampleTags, true))) {
                return $this->increaseEngine($engineId, $completion, $blockText, $title, $documentText, $url, $promptType);
            }
            // Never have a string longer than 3 words - anything longer than 3 words in tags SUCK
            if (str_word_count($tag) > 3) {
                unset($tags[$tagKey]);
            }
        }

        return $this->cleanTags($tags, $title, $documentText, $url);
    }
}
