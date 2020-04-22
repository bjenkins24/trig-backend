<?php

namespace App\Support\Traits\Relationships;

trait HasCardIntegration
{
    /**
     * Has one card integrations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cardIntegration()
    {
        return $this->hasOne('App\Models\CardIntegration');
    }
}
