<?php

namespace App\Modules\Tag;

use App\Models\Tag;

class TagRepository
{
    // I need to count the tokens in the tag and in the found tag to get an exact match in elastic search
    // This was really painful, but I couldn't find a good solution in elastic search natively
    // something like this seemed promising: https://stackoverflow.com/questions/30517904/elasticsearch-exact-matches-on-analyzed-fields?rq=1
    // but to no avail
    public function countTokens(string $tag): int
    {
        if (! $tag) {
            return 0;
        }
        // These need to match create_tags_index.php in elastic search migrations
        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for',
            'if', 'in', 'into', 'is', 'it', 'of', 'on', 'or', 'such', 'that',
            'the', 'their', 'then', 'there', 'these', 'they', 'this', 'to',
            'was', 'will', 'with',
        ];

        $tag = str_replace(['-', '/', '|'], ' ', $tag);

        // Remove stop words
        $words = explode(' ', strtolower($tag));
        foreach ($words as $tagWordKey => $tagWord) {
            if (in_array($tagWord, $stopWords, true)) {
                unset($words[$tagWordKey]);
            }
        }

        return count($words);
    }

    /**
     * Find if a tag exists that matches a string exactly, or very closely
     * For example: If the tag "Games" exists and we pass in "Game" this will find "Games".
     */
    public function findSimilar(string $tag, int $workspaceId): ?Tag
    {
        $existingTag = Tag::where('tag', $tag)->where('workspace_id', $workspaceId)->first();
        if ($existingTag) {
            return $existingTag;
        }

        $results = Tag::rawSearch()
            ->query([
                'bool' => [
                    'must' => [
                        'match_phrase' => [
                            'tag' => $tag,
                        ],
                    ],
                    'filter' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'workspace_id' => $workspaceId,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])->raw();

        if (isset($results['hits']) && $results['hits']['total']['value'] > 0) {
            foreach ($results['hits']['hits'] as $hit) {
                if ($this->countTokens($hit['_source']['tag']) === $this->countTokens($tag)) {
                    return Tag::find($hit['_source']['id']);
                }
            }
        }

        return null;
    }
}
