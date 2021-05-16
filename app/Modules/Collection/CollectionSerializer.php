<?php

namespace App\Modules\Collection;

use App\Models\Collection;

class CollectionSerializer
{
    public function serialize(Collection $collection): array
    {
        return [
            'data' => [
                'id'          => $collection->id,
                'user_id'     => $collection->user_id,
                'title'       => $collection->title,
                'description' => $collection->description,
                'slug'        => $collection->slug,
                'token'       => $collection->token,
            ],
        ];
    }
}
