<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\MorphOne;

trait LinkShareable
{
    /**
     * Get the owning link share setting model.
     */
    public function linkShareSetting(): MorphOne
    {
        return $this->morphOne('App\Models\LinkShareSetting', 'shareable');
    }
}
