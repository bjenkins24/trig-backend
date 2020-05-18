<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCapability;
use App\Support\Traits\Relationships\BelongsToLinkShareType;
use App\Support\Traits\Relationships\PermissionTypeable;

class LinkShareSetting extends BaseModel
{
    use PermissionTypeable;
    use BelongsToCapability;
    use BelongsToLinkShareType;

    /**
     * Get the owning linkshareable model.
     */
    public function linkShareable()
    {
        return $this->morphTo();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['link_share_type_id', 'capability_id'];
}
