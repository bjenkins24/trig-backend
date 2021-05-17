<?php

namespace App\Modules\Collection;

use App\Models\Collection;
use App\Modules\Permission\PermissionSerializer;

class CollectionSerializer
{
    private PermissionSerializer $permissionSerializer;

    public function __construct(PermissionSerializer $permissionSerializer)
    {
        $this->permissionSerializer = $permissionSerializer;
    }

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
                'permissions' => $this->permissionSerializer->serialize($collection),
            ],
        ];
    }
}
