<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCapability
{
    /**
     * Has one capability.
     */
    public function capability(): HasOne
    {
        return $this->hasOne('App\Models\Capability');
    }
}
