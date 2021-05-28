<?php

namespace App\Modules\Collection;

use App\Models\Collection;
use App\Modules\Permission\PermissionSerializer;

class CollectionSerializer
{
    private PermissionSerializer $permissionSerializer;
    private CollectionRepository $collectionRepository;

    public function __construct(
        CollectionRepository $collectionRepository,
        PermissionSerializer $permissionSerializer
    ) {
        $this->permissionSerializer = $permissionSerializer;
        $this->collectionRepository = $collectionRepository;
    }

    public function serialize(Collection $collection): array
    {
        return [
            'id'          => $collection->id,
            'user_id'     => $collection->user_id,
            'token'       => $collection->token,
            'title'       => $collection->title,
            'description' => $collection->description,
            'totalCards'  => $this->collectionRepository->getTotalCards($collection),
            'permissions' => $this->permissionSerializer->serialize($collection),
        ];
    }
}
