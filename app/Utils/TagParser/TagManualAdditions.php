<?php

namespace App\Utils\TagParser;

class TagManualAdditions
{
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
        [['cognitive bias', '~cognitive bias', '~cognitive biases'], 'Cognitive Bias'],
        [['confirmation bias', '~confirmation bias', '~confirmation biases'], 'Confirmation Bias'],
        [['customer service', '~customer services'], 'Customer Service'],
        [[' MVP', 'MVP ', '~minimum viable product'], 'MVP'],
        [['machine learn', '~deep learning'], 'Machine Learning'],
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
        [['HR ', ' HR', 'human resource manag', '~HR'], 'Human Resources'],
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
        [['recipe', '~recipes'], 'Recipe'],
        [['diy ', ' diy', 'do it yourself', '~do it yourself'], 'DIY'],
        // No parent - could be Parent Company for example
        [['parenting', '~parent'], 'Parenting'],
        [['romance', '~romance', 'romancing', '~romancing', 'romantic', '~romantic'], 'Romance'],
        ['self-', 'Self-Help'],
        [['dnd', 'd&d', '~dnd'], 'D&D'],
        [['children', 'kids', 'kid', 'child', '~child', '~kid', '~children'], 'Kids'],
    ];

    /**
     * Basically this checkes our HIGH_LEVEL_TAGS above and adds tags that make sense based
     * on the content of a tag.
     *
     * I know this method looks bad. But this is the real world not some leetcode test.
     * This is not going to go through billions of documents. Just a few items. And it does it in a queue.
     * Unless you know _for sure_ that this code is a bottleneck, please just leave this alone.
     * It's fine. Don't waste your time.
     */
    public function addHighLevelTags(array $tags): array
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
                                if (strtolower($removalString) === strtolower($tag)) {
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

    /**
     * Remove any manually tagged high level tags (hypernyms) from the tag array in case we need to do something
     * with _just_ the automatically generated tags.
     */
    public function removeHighLevelTags(array $tags): array
    {
        foreach (self::HIGH_LEVEL_TAGS as $highLevelTag) {
            foreach ($tags as $tagKey => $tag) {
                if (strtolower($tag) === strtolower($highLevelTag[1])) {
                    unset($tags[$tagKey]);
                }
            }
        }

        return $tags;
    }
}
