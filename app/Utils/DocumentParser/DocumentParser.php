<?php

namespace App\Utils\DocumentParser;

use App\Utils\Gtp3;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentParser
{
    /**
     * These characters are _not_ allowed in any tags and will be removed.
     */
    private const BANNED_CHARS = [
        '#', '~',
    ];

    /**
     * These are tags we're going to add by themselves if they are included in any other tags. We want duplicate
     * tags for documents that are similar. The first element in the array is the base word we're matching for, the second element
     * is the name of the actual tag we're going to include. Ideally this list gets really really really long.
     *
     * If you include a "-" sign at the beginning of the matching string it will _not_ match a string with that included
     * The first element can also be an array of strings
     *
     * If you want to remove an _entire_ tag if it exist and REPLACE it with the high level one here, then use a ~
     *
     * @var array|string[]
     */
    private const HIGH_LEVEL_TAGS = [
        // Business related
        [['accountant', 'accounting', '~accountant'], 'Accounting'],
        ['sale', 'Sales'],
        ['marketing', 'Marketing'],
        ['culture', 'Culture'],
        ['customer support', 'Customer Support'],
        ['customer service', 'Customer Service'],
        [[' ML', 'ML ', 'machine learning'], 'Machine Learning'],
        [[' AI', 'AI ', 'artificial intelligence'], 'Artificial Intelligence'],
        [['risk manag', '~risk manager', '~risk managers'], 'Risk Management'],
        [['data scien', '~data scientist'], 'Data Science'],
        [['software engineer', 'software develop', '~software engineer', '~software engineers', '~software developer', '~software developers', '~software developing'], 'Software Engineering'],
        [['hardware engineer', 'hardware develop', '~hardware engineer', '~hardware engineers', '~hardware developer', '~hardware developers', '~hardware developing'], 'Hardware Engineering'],
        [['design', '~designer', '~designing'], 'Design'],
        [['manag', '-product', '~managers', '~manager', '~managing'], 'Management'],
        [['lead', '~leader'], 'Leadership'],
        [['strateg', '~strategize'], 'Strategy'],
        [['entrepreneur', '~entrepreneur'], 'Entrepreneurship'],
        [['HR ', ' HR', 'human resource manag', '~HR'], 'Human Resource Management'],
        [['producti', '~productive'], 'Productivity'],
        [['economic', '~economic'], 'Economics'],
        [['brand', '-brands', '~brand'], 'Branding'],
        [['budget', '~budget'], 'Budgeting'],
        [['divers', '~diverse'], 'Diversity'],
        [['educat', '~educator'], 'Education'],
        ['health', 'Health'],
        [['hiring', 'hire', '~hire'], 'Hiring'],
        [['meeting', '~meet'], 'Meetings'],
        [['ethic', '~ethical'], 'Ethics'],
        [['pric', '~prices'], 'Pricing'],
        [['real estate, realtor'], 'Real Estate'],
        [['tech', '~tech'], 'Technology'],
        // Consumer related
        [['recipe', '~recipe'], 'Recipes'],
        [['diy', 'do it yourself', '~do it yourself'], 'DIY'],
        // No parent - could be Parent Company for example
        [['parenting', '~parent'], 'Parenting'],
        [['romance', 'romancing', '~romancing', 'romantic', '~romantic'], 'Romance'],
    ];
    private Gtp3 $gtp3;

    public function __construct(Gtp3 $gtp3)
    {
        $this->gtp3 = $gtp3;
    }

    private function tableToArray(string $completion): array
    {
        $potentialTags = explode(',', $completion);
        $tags = [];
        foreach ($potentialTags as $tag) {
            if (trim($tag)) {
                $tags[] = trim($tag);
            }
        }

        return $tags;
    }

    /**
     * Basically this checkes our HIGH_LEVEL_TAGS above and adds tags that make sense based
     * on the content of a tag.
     *
     * I know this method looks bad. But this is the real world not some leetcode test.
     * This is not going to go through billions of documents. Just a few items. And it does it in a queue.
     * Unless you know _for sure_ that this code is a bottleneck, please just leave this alone.
     * It's fine. Don't waste your time.
     */
    private function addHighLevelTags(array $tags): array
    {
        $newTags = $tags;
        // We reverse the tags first because we want to add the tags in reverse order to keep the
        // order they came in
        foreach (array_reverse($tags) as $tag) {
            foreach (self::HIGH_LEVEL_TAGS as $highLevelTag) {
                if (is_array($highLevelTag[0])) {
                    // Get negative strings
                    $negativeStrings = [];
                    foreach ($highLevelTag[0] as $possibleString) {
                        if ('-' === $possibleString[0]) {
                            $negativeStrings[] = ltrim($possibleString, '-');
                        }
                    }

                    // Get removal strings
                    $removalStrings = [];
                    foreach ($highLevelTag[0] as $possibleString) {
                        if ('~' === $possibleString[0]) {
                            $removalStrings[] = ltrim($possibleString, '~');
                        }
                    }

                    foreach ($highLevelTag[0] as $possibleString) {
                        if ('-' === $possibleString[0] || '~' === $possibleString[0]) {
                            continue;
                        }
                        // is an array - not a negative string and positive string exists
                        $shouldAdd = true;
                        $shouldRemove = false;
                        if (false !== stripos($tag, $possibleString)) {
                            foreach ($negativeStrings as $negativeString) {
                                if (false !== stripos($tag, $negativeString)) {
                                    $shouldAdd = false;
                                }
                            }
                            foreach ($removalStrings as $removalString) {
                                if ($removalString === strtolower($tag)) {
                                    $shouldRemove = true;
                                }
                            }
                            if ($shouldAdd) {
                                array_unshift($newTags, $highLevelTag[1]);
                            }
                            if ($shouldRemove) {
                                foreach ($newTags as $newTagKey => $newTag) {
                                    if ($newTag === $tag) {
                                        unset($newTags[$newTagKey]);
                                    }
                                }
                            }
                        }
                    }
                } elseif (false !== stripos($tag, $highLevelTag[0])) {
                    array_unshift($newTags, $highLevelTag[1]);
                }
            }
        }

        return array_values(array_unique($newTags));
    }

    public function cleanTags(array $tags, int $engineId = 2): array
    {
        if (empty($tags)) {
            return [];
        }

        $badWords = [
            'good', 'great', 'bad', 'sensational', 'amazing', 'perfect', 'incredible',
        ];

        // Remove words
        foreach ($tags as $tagKey => $tag) {
            foreach ($badWords as $badWord) {
                if (false !== strpos($tag, $badWord)) {
                    $tag = str_replace($badWord, '', $tag);
                }
            }
        }

        $table = implode(', ', $tags);

        $prompt = <<<PROMPT
sensational pioneers, great pioneer leaders, pioneers, amazing pioneers, incredible pioneers, fun pioneers, great pioneer darts, the pioneer tree, pioneer leadership

Clean up the keywords above:
| Pioneer | Pioneer Leader | Pioneer | Pioneer | Pioneer | Pioneer | Pioneer Dart | The Pioneer Tree | Pioneer Leader |


$table

Clean up the keywords above:
|
PROMPT;

        try {
            $response = $this->gtp3->complete($prompt, [
                'max_tokens'        => 20,
                'temperature'       => 0.2,
                'top_p'             => 0,
                'frequency_penalty' => 1,
                'presence_penalty'  => 1,
            ], $engineId);
        } catch (Exception $exception) {
            Log::notice('There was a problem with the response from GTP3: '.$exception->getMessage());

            return [];
        }

        if (empty($response['choices']) || empty($response['choices'][0]) || empty($response['choices'][0]['text'])) {
            Log::notice('There was a problem with the response from GTP3: '.json_encode($response));

            return [];
        }

        $cleanedTags = array_slice($this->tableToArray($response['choices']['0']['text']), 0, count($tags));
        foreach ($cleanedTags as $tagKey => $tag) {
            // If It has pioneer in it it failed
            if (false !== stripos($tag, 'pioneer')) {
                unset($cleanedTags[$tagKey]);
            }
        }

        return $cleanedTags;
    }

    private function removeBannedChars($tags): array
    {
        $newTags = [];
        foreach ($tags as $tag) {
            $newTag = $tag;
            foreach (self::BANNED_CHARS as $bannedChar) {
                $newTag = str_replace($bannedChar, '', $newTag);
            }
            $newTags[] = $newTag;
        }

        return $newTags;
    }

    public function getTags(string $documentText, $engineId = 1): Collection
    {
        if (! $documentText) {
            return collect([]);
        }

        $truncatedDocumentText = Str::truncateOnWord(Str::removeLineBreaks($documentText), 1600);
        $exampleTags = ['Drip Irrigation', 'Covid 19', 'Sprinkler System', 'Water Waste'];
        $list = implode(', ', $exampleTags);

        $prompt = <<<PROMPT
Text: **Drip irrigation** is a system of tubing that directs __small quantities__ of water precisely where it’s needed, preventing the water waste associated with sprinkler systems. Drip systems minimize water runoff, evaporation, and wind drift by delivering a slow, uniform stream of water either above the soil surface or directly to the root zone.
Tags: $list
###
Text: $truncatedDocumentText
Tags:
PROMPT;

        try {
            $response = $this->gtp3->complete($prompt, [
                'max_tokens'        => 24,
                'temperature'       => 0.2,
                'top_p'             => 0.5,
                'frequency_penalty' => 0.8,
                'presence_penalty'  => 0.1,
                'stop'              => '###',
            ], $engineId);
        } catch (Exception $exception) {
            Log::notice('There was a problem with the response from GTP3: '.$exception->getMessage());

            return collect([]);
        }

        if (empty($response['choices']) || empty($response['choices'][0]) || empty($response['choices'][0]['text'])) {
            Log::notice('There was a problem with the response from GTP3: '.json_encode($response));

            return collect([]);
        }

        $completion = $response['choices'][0]['text'];
        $tags = $this->tableToArray($completion);

        $nextEngineNoticeMessage = '';
        if ($engineId < 3) {
            $nextEngineNoticeMessage = "Trying {$this->gtp3->getEngine($engineId + 1)} engine for tag generation. Got $completion from $truncatedDocumentText";
        }
        // If the result includes the example tags then the tag retrieval didn't work. Let's try a better engine
        foreach ($tags as $tagKey => $tag) {
            // Bad results tend to have 4 words or more in tags - but let's only go up to curie for this
            // since it _is_ possible to get 4 words legitimately
            if ($engineId < 2 && str_word_count($tag) > 3) {
                Log::notice($nextEngineNoticeMessage);

                return $this->getTags($documentText, $engineId + 1);
            }
            if (in_array($tag, $exampleTags, true)) {
                if (3 !== $engineId) {
                    Log::notice($nextEngineNoticeMessage);

                    return $this->getTags($documentText, $engineId + 1);
                }

                // We tried davinci and _still_ got the example tags. Let's just remove them. We tried our best
                // This also means nothing will _ever_ be tagged as our example tags. For now I think that's ok. I mean
                // What are the odds that we get sprinkler system in here? Even if we do, worst case is no one gets
                // articles tagged as sprinkler system. I can live with that
                unset($tags[$tagKey]);
            }
            // Never have a string longer than 4 words - it's not improbbable to get a sentence in here on accident
            if (str_word_count($tag) > 4) {
                unset($tags[$tagKey]);
            }
        }

        // Only get three tags - anything more could get weird
        return collect($this->addHighLevelTags($this->removeBannedChars(array_slice($tags, 0, 3))));
    }
}
