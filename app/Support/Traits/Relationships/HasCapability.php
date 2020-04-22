<?php

namespace App\Support\Traits\Relationships;

trait HasCapability
{
    /**
     * Has one capability.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function capability()
    {
        return $this->hasOne('App\Models\Capability');
    }
}
