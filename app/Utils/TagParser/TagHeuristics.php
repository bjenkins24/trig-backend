<?php

namespace App\Utils\TagParser;

class TagHeuristics
{
    /**
     * If the content, title, or URL contain a certain string let's just auto tag it. This is how a human
     * would do it.
     */
    private const HEURISTICS = [
        [
            'title' => ['Amazon.com: Books'],
            'tag'   => 'Book',
        ],
        [
            'title' => ['sale'],
            'tag'   => 'Sales',
        ],
        [
            'url' => [
                'allrecipes.com',
                'yummly.com',
                'epicurious.com',
                'tasty.co',
                'spoonacular.com',
                'delish.com',
                'edamam.com',
                'pinchofyum.com',
                'recipetineats.com',
                'foodnetwork.com',
                'souldeliciouz',
                'turkishfood',
                'cooked.com',
                'gimmesomeoven.com',
            ],
            'tag' => 'Recipe',
        ],
    ];

    /**
     * Add tags based off of just simple string matching in the title or content.
     */
    public function addHeuristicTags(array $tags, ?string $title = '', ?string $content = '', ?string $url = ''): array
    {
        $newTags = $tags;
        foreach (self::HEURISTICS as $heuristic) {
            if (! empty($heuristic['title'])) {
                foreach ($heuristic['title'] as $testTitle) {
                    if (false !== stripos($title, $testTitle)) {
                        $newTags[] = $heuristic['tag'];
                    }
                }
            }
            if (! empty($heuristic['url'])) {
                foreach ($heuristic['url'] as $testUrl) {
                    if (false !== stripos($url, $testUrl)) {
                        $newTags[] = $heuristic['tag'];
                    }
                }
            }
        }

        return $newTags;
    }

    /**
     * Remove any manually tagged heuristics from the tag array in case we need to do something
     * with _just_ the automatically generated tags.
     */
    public function removeHeuristicTags(array $tags): array
    {
        foreach (self::HEURISTICS as $heuristic) {
            foreach ($tags as $tagKey => $tag) {
                if (strtolower($heuristic['tag']) === strtolower($tag)) {
                    unset($tags[$tagKey]);
                }
            }
        }

        return $tags;
    }
}
