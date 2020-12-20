<?php

namespace App\Utils\TagParser;

class TagHeuristics
{
    /**
     * If the content or the title contain a certain string let's just auto tag it. This is how a human
     * would do it.
     */
    private const HEURISTICS = [
        [
            'title' => 'Amazon.com: Books',
            'tag'   => 'Book',
        ],
        [
            'title' => 'sale',
            'tag'   => 'Sales',
        ],
    ];

    /**
     * Add tags based off of just simple string matching in the title or content.
     */
    public function addHeuristicTags(array $tags, ?string $title = '', ?string $content = ''): array
    {
        $newTags = $tags;
        foreach (self::HEURISTICS as $heuristic) {
            if (! empty($heuristic['title']) && false !== stripos($title, $heuristic['title'])) {
                $newTags[] = $heuristic['tag'];
            }
        }

        return $newTags;
    }
}
