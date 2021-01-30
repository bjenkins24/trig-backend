<?php

namespace App\Utils\TagParser;

class TagStringRemoval
{
    /**
     * These characters are _not_ allowed in any tags and will be removed.
     */
    private const BANNED_STRINGS = [
        '#', '~', '.com', '. com',
        // Removing a single apostrophe is an interesting choice here. Let me justify myself
        // This makes some tags grammatically incorrect "Adventurer's League" becomes "Adventurers League"
        // Sometimes GPT3 doesn't know how to hand the apostrophe so we will get "Adventurers' League" as well
        // which really screws up our tag overlap. I'd rather have tag overlap than this small grammar issue
        '\'',
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
        'cash', 'business', 'flexibility', 'time', 'PM', 'consistency', 'cheats', 'cheat', 'best practices', 'best practice', 'new', 'company', 'human',
        'cookies', /* cookies is not so horrible but it comes up for "we use cookies" websites so gotta remove it */
    ];

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
     *
     * But if it does this:
     * Covid 19, Covid 20, Covid 21 we only want to remove the last two
     */
    public function removeConsecutiveNumbers(array $tags): array
    {
        $newTags = $tags;
        $lastNumber = false;
        $firstMatch = ['tag' => '', 'tagKey' => ''];
        $pattern = '/(\d+)$/';
        foreach ($tags as $tagKey => $tag) {
            if (preg_match($pattern, $tag, $matches)) {
                if (! $firstMatch['tag']) {
                    $firstMatch = [
                        'tag'    => $tag,
                        'tagKey' => $tagKey,
                    ];
                }
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

        if (0 === $firstMatch['tagKey']) {
            array_unshift($newTags, $firstMatch['tag']);
        } elseif ($firstMatch['tagKey']) {
            $removedNumber = preg_replace('/(\d+)$/', '', $tags[$firstMatch['tagKey']]);
            if ($removedNumber !== $tags[$firstMatch['tagKey'] - 1]) {
                array_unshift($newTags, $firstMatch['tag']);
            }
        }

        return $newTags;
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

    public function remove(array $tags): array
    {
        $newTags = $tags;
        $newTags = $this->removeBadWords($newTags);
        $newTags = $this->removeConsecutiveNumbers($newTags);
        $newTags = $this->removeBanned($newTags);

        return $newTags;
    }
}
