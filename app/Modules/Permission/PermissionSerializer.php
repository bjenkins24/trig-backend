<?php

namespace App\Modules\Permission;

use App\Models\Card;
use App\Models\Collection;

class PermissionSerializer
{
    /**
     * @param Collection|Card $type
     *
     * @return array[]
     */
    public function serialize($type): array
    {
        $linkSharing = $type->linkShareSetting()->get();
        $results = [];
        foreach ($linkSharing as $linkShare) {
            $results[] = ['type' => $linkShare->linkShareType()->first()->name, 'capability' => $linkShare->capability()->first()->name];
        }

        return $results;
    }
}
