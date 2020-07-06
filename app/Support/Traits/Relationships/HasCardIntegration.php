<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardIntegration
{
    /**
     * Has one card integration.
     */
    public function cardIntegration(): HasOne
    {
        return $this->hasOne('App\Models\CardIntegration');
    }
}
