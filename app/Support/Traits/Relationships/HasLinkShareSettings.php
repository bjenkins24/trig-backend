<?php

namespace App\Support\Traits\Relationships;

trait HasLinkShareSettings
{
    /**
     * Has many link share settings.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function linkShareSettings()
    {
        return $this->hasMany('App\Models\LinkShareSetting');
    }
}
