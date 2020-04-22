<?php

namespace App\Support\Traits\Relationships;

trait HasLinkSharingSettings
{
    /**
     * Has many link sharing settings.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function linkSharingSettings()
    {
        return $this->hasMany('App\Models\LinkSharingSetting');
    }
}
