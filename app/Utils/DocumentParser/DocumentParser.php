<?php

namespace App\Utils\DocumentParser;

use App\Utils\Gpt3;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentParser
{
    /**
     * These characters are _not_ allowed in any tags and will be removed.
     */
    private const BANNED_STRINGS = [
        '#', '~', '.com',
    ];

    /**
     * These are tags that if they exist by themselves indicate that GPT-3 did a poor job and to increase the engine size.
     * If they keep showing up by themselves, they will be removed.
     */
    private const BAD_TAGS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'if', 'in', 'into', 'is', 'it', 'no', 'not', 'of', 'on', 'or', 'such', 'that', 'the', 'their', 'then', 'there', 'these', 'they', 'this', 'to', 'was', 'will', 'with',
    ];

    /**
     * These are words that BY THEMSELVES make horrible tags. If any tag matches
     * these tags exactly (all these words must be all lower case), we're just going to remove them outright.
     */
    private const BANNED_TAGS = [
        'cash', 'business', 'flexibility', 'time', 'PM', 'consistency', 'cheats', 'cheat', 'best practices', 'best practice',
    ];

    /**
     * If the content or the title contain a certain string let's just auto tag it. This is how a human
     * would do it.
     */
    private const HUERISTICS = [
        [
            'title' => 'Amazon.com: Books',
            'tag'   => 'Book',
        ],
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
        [['culture', '~cultures'], 'Culture'],
        [['book', '~books'], 'Book'],
        ['customer support', 'Customer Support'],
        [['customer service', '~customer services'], 'Customer Service'],
        [[' MVP', 'MVP '], 'MVP'],
        [[' ML', 'ML ', 'machine learning'], 'Machine Learning'],
        [[' AI', 'AI ', 'artificial intelligence'], 'Artificial Intelligence'],
        [['risk manag', '~risk manager', '~risk managers'], 'Risk Management'],
        [['data scien', '~data scientist', '~data scientists'], 'Data Science'],
        [['software engineer', 'software develop', '~software engineer', '~software engineers', '~software developer', '~software developers', '~software developing', '~software development'], 'Software Engineering'],
        [['hardware engineer', 'hardware develop', '~hardware engineer', '~hardware engineers', '~hardware developer', '~hardware developers', '~hardware developing', '~hardware development'], 'Hardware Engineering'],
        [['design', '~designer', '~designing'], 'Design'],
        [['product', '-manag', '~products'], 'Product'],
        [['product manag', '~product manager', '~product managers'], 'Product Management'],
        [['manag', '-risk', '-product', '-sale', '~managers', '~manager', '~managing'], 'Management'],
        [['lead', '~leader'], 'Leadership'],
        [['strateg', '~strategize'], 'Strategy'],
        [['entrepreneur', '~entrepreneur', '~entrepreneurial'], 'Entrepreneurship'],
        [['HR ', ' HR', 'human resource manag', '~HR'], 'Human Resource Management'],
        [['producti', '~productive'], 'Productivity'],
        [['economic', '~economic'], 'Economics'],
        [['coach', '~coach', '~coaches', '~coachable'], 'Coaching'],
        [['brand', '-brands', '~brand'], 'Branding'],
        [['budget', '~budget'], 'Budgeting'],
        [['divers', '~diverse'], 'Diversity'],
        [['educat', '~educator'], 'Education'],
        ['health', 'Health'],
        [['hiring', 'hire', '~hire'], 'Hiring'],
        [['meeting', '~meet', '~meeting'], 'Meetings'],
        [['ethic', '~ethical'], 'Ethics'],
        [['pric', '~prices'], 'Pricing'],
        [['real estate, realtor'], 'Real Estate'],
        [['tech', '~tech'], 'Technology'],
        // Consumer related
        [['recipe', '~recipe'], 'Recipes'],
        [['diy', 'do it yourself', '~do it yourself'], 'DIY'],
        // No parent - could be Parent Company for example
        [['parenting', '~parent'], 'Parenting'],
        [['romance', '~romance', 'romancing', '~romancing', 'romantic', '~romantic'], 'Romance'],
        ['self-', 'Self-Help'],
    ];
    private Gpt3 $gpt3;

    public function __construct(Gpt3 $gpt3)
    {
        $this->gpt3 = $gpt3;
    }

    public function removeBadWords(array $tags): array
    {
        $newTags = $tags;
        foreach ($tags as $tagKey => $tag) {
            if (in_array($tag, self::BAD_TAGS, true)) {
                unset($newTags[$tagKey]);
            }
        }

        return $newTags;
    }

    /**
     * Sometimes GPT3 will get away with itself and start counting like this:
     * Audible, Audible2, Audible3
     * These are _horrible_ tags and should be removed.
     */
    public function removeConsecutiveNumbers(array $tags): array
    {
        $newTags = $tags;
        $lastNumber = false;
        foreach ($tags as $tagKey => $tag) {
            if (preg_match('/(\d+)$/', $tag, $matches)) {
                if ($lastNumber + 1 === (int) $matches[1]) {
                    unset($newTags[$tagKey], $newTags[$tagKey - 1]);
                }
                if (false === $lastNumber) {
                    $lastNumber = (int) $matches[1];
                } else {
                    ++$lastNumber;
                }
            }
        }

        return $newTags;
    }

    private function tableToArray(string $completion): array
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

    /**
     * Add tags based off of just simple string matching in the title or content.
     */
    private function addHeuristicTags(array $tags, ?string $title = '', ?string $content = ''): array
    {
        foreach (self::HUERISTICS as $hueristic) {
            if (! empty($hueristic['title']) && false !== stripos($title, $hueristic['title'])) {
                $tags[] = $hueristic['tag'];
            }
            if (! empty($hueristic['content']) && false !== stripos($content, $hueristic['content'])) {
                $tags[] = $hueristic['tag'];
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

        return array_values($newTags);
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
            $response = $this->gpt3->complete($prompt, [
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

    private function removeBanned($tags): array
    {
        $newTags = [];
        foreach ($tags as $tag) {
            $newTag = $tag;
            foreach (self::BANNED_STRINGS as $bannedStrings) {
                $newTag = str_replace($bannedStrings, '', $newTag);
            }
            $newTags[] = $newTag;
        }

        foreach ($tags as $tagKey => $tag) {
            foreach (self::BANNED_TAGS as $bannedTag) {
                if ($bannedTag === strtolower(trim($tag))) {
                    unset($newTags[$tagKey]);
                }
            }
        }

        return $newTags;
    }

    public function oldPrompt(string $title, string $text): string
    {
        $exampleTags = ['Aliens', 'UFO'];
        $exampleTags2 = ['Drip Irrigation', 'Sprinkler System', 'Covid 19', 'Water Waste'];
        $exampleTags3 = ['Laundry', 'Dryer Sheet', 'Toxic Chemicals'];
        $list = implode(', ', $exampleTags);
        $list2 = implode(', ', $exampleTags2);
        $list3 = implode(', ', $exampleTags3);

        $oldPrompt = <<<PROMPT
Title: UFOs Among Us
Markdown: In the 1940s and 50s reports of __"flying saucers"__ became an American cultural **phenomena**. Sightings of strange objects in the sky became the raw materials for Hollywood to present visions of potential threats. Amy Franko wanted to verify that there were extraterrestrials.
Tags: $list
###
Title: How to Use Drip Irrigation to Water Your Garden
Markdown: **Drip irrigation** is a system of tubing that directs __small quantities__ of water precisely where it’s needed says Brian Jenkins, preventing the water waste associated with sprinkler systems. Since Covid 19, Joseph Goldberg mentions that drip systems minimize water runoff, evaporation, and wind drift by delivering a slow, uniform stream of water either above the soil surface or directly to the root zone.
Tags: $list2
###
Title: "Greener" Laundry by the Load: Fabric Softener versus Dryer Sheets
Markdown: If you’re concerned about the health and safety of your family members, you might want to stay away from both conventional dryer sheets and liquid fabric softeners altogether. While it may be nice to have clothes that feel soft, smell fresh and are free of static cling, both types of products contain chemicals known to be toxic to people after sustained exposure. According to the health and wellness website Sixwise.com, some of the most harmful ingredients in dryer sheets and liquid fabric softener alike include benzyl acetate (linked to pancreatic cancer), benzyl alcohol (an upper respiratory tract irritant), ethanol (linked to central nervous system disorders), limonene (a known carcinogen) and chloroform (a neurotoxin and carcinogen), among others.
Tags: $list3
###
Title: $title
Markdown: $text
Tags:
PROMPT;

        return $oldPrompt;
    }

    private function increaseEngine(int $engineId, string $completion, string $input, string $title, $documentText): Collection
    {
        $nextEngineNoticeMessage = '';
        if ($engineId < 3) {
            $shortInputForLog = Str::truncateOnWord($input, 200);
            $nextEngineNoticeMessage = "Trying {$this->gpt3->getEngine($engineId + 1)} engine for tag generation. Got $completion from $shortInputForLog";
        }

        Log::notice($nextEngineNoticeMessage);

        return $this->getTags($title, $documentText, $engineId + 1);
    }

    /**
     * This will take a formatted string with lists, random line breaks, or just a poorly written document and clean
     * it up to a single raw block of text.
     */
    private function docToBlock(string $rawText, int $totalCharacters = 2500): string
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

    public function getTags(string $title, string $documentText, $engineId = 1): Collection
    {
        if (! $documentText || ! $title) {
            return collect([]);
        }

        $blockText = $this->docToBlock($documentText);

        $prompt = <<<PROMPT
$blockText

Tags:
PROMPT;

        try {
            $response = $this->gpt3->complete($prompt, [
                'max_tokens'        => 24,
                'temperature'       => 0,
                'top_p'             => 0.2,
                'frequency_penalty' => 0.8,
                'presence_penalty'  => 0.2,
                'stop'              => '\n',
            ], $engineId);
        } catch (RequestException $exception) {
            Log::notice('GPT has failed to load: '.$exception->getMessage());

            return collect([]);
        } catch (Exception $exception) {
            Log::notice('There was unexpected problem with the response from GTP3: '.$exception->getMessage());

            return collect([]);
        }

        if (empty($response['choices']) || empty($response['choices'][0]) || empty($response['choices'][0]['text'])) {
            Log::notice('There was a problem with the response from GTP3: '.json_encode($response));

            return collect([]);
        }

        $completion = $response['choices'][0]['text'];
        $tags = $this->tableToArray($completion);

        $isCounting = count($this->removeConsecutiveNumbers($tags)) !== count($tags);
        $hasBadWords = count($this->removeBadWords($tags)) !== count($tags);

        // If there are consecutive numbers or we words on that bad list - let's try a new engine - that means it stunk
        if (3 !== $engineId && ($isCounting || $hasBadWords)) {
            return $this->increaseEngine($engineId, $completion, $blockText, $title, $documentText);
        }

        // If the result includes the example tags then the tag retrieval didn't work. Let's try a better engine
        foreach ($tags as $tagKey => $tag) {
            // Title case each tag:
            $tags[$tagKey] = Str::title($tag);

            // Bad results tend to have 4 words or more - let's just go to currie here as davinci will often
            // get 4 words too - and this is fairly common. Save on cost
            if ($engineId < 2 && str_word_count($tag) > 3) {
                return $this->increaseEngine($engineId, $completion, $blockText, $title, $documentText);
            }
            // Never have a string longer than 3 words - anything longer than 3 words in tags SUCK
            if (str_word_count($tag) > 3) {
                unset($tags[$tagKey]);
            }
        }

        return collect(
            array_values(
                array_unique(
                    $this->removeBadWords(
                        $this->removeConsecutiveNumbers(
                            $this->addHeuristicTags(
                                $this->addHighLevelTags(
                                    $this->removeBanned($tags)
                                ), $title, $documentText
                            )
                        )
                    )
                )
            )
        );
    }
}
