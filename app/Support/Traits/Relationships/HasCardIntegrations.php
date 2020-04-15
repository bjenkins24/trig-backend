<?php

namespace App\Support\Traits\Relationships;

trait HasCardIntegrations
{
    /**
     * Has many card integrations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cardIntegrations()
    {
        return $this->hasOne('App\Models\CardIntegrations');
    }
}
