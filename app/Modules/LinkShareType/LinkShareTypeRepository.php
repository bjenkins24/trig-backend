<?php

namespace App\Modules\LinkShareType;

use App\Models\LinkShareType;

class LinkShareTypeRepository
{
    const ANYONE = 'anyone';
    const ANYONE_ORGANIZATION = 'anyoneInOrganization';
    const PUBLIC_SHARE = 'public';

    /**
     * Get a LinkShareType.
     *
     * @param [string] $LinkShareType like reader or writer
     *
     * @return void
     */
    public function get(string $name): LinkShareType
    {
        return LinkShareType::where(['name' => $name])->firstOrFail();
    }
}
