<?php

namespace App\Utils\TagParser;

class TagUtils
{
    public function completionToArray(string $completion): array
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
}
