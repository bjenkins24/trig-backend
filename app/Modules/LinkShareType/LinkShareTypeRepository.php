<?php

namespace App\Modules\LinkShareType;

use App\Models\LinkShareType;

class LinkShareTypeRepository
{
    public const ANYONE = 'anyone';
    public const ANYONE_ORGANIZATION = 'anyoneInWorkspace';
    public const PUBLIC_SHARE = 'public';

    /**
     * Get a LinkShareType.
     */
    public function get(string $name): LinkShareType
    {
        return LinkShareType::where(['name' => $name])->firstOrFail();
    }
}
