<?php

namespace App\Modules\Tag;

use App\Models\Tag;
use App\Utils\TagParser\TagHypernym;
use Illuminate\Support\Collection;

class TagService
{
    /**
     * Find if any of these hypernyms have been used before. If they have, then turn those hypernyms to tags
     * and this one too.
     */
    public function useHypernyms(int $workspaceId, Collection $hypernyms): Collection
    {
        $newTags = collect([]);
        $hypernyms->each(static function ($hypernym) use ($newTags, $workspaceId) {
            // If the hypernym is whitelisted just turn it into a tag already
            if (in_array(strtolower($hypernym), TagHypernym::WHITELISTED_HYPERNYMS, true)) {
                $newTags->add($hypernym);
            }
            $exists = Tag::where('workspace_id', $workspaceId)->where('tag', $hypernym)->exists();
            if ($exists) {
                $newTags->add($hypernym);
            }
        });

        return $newTags;
    }
}
