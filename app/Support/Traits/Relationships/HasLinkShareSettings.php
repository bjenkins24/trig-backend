<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasLinkShareSettings
{
    /**
     * Has many link share settings.
     */
    public function linkShareSettings(): HasMany
    {
        return $this->hasMany('App\Models\LinkShareSetting');
    }
}
