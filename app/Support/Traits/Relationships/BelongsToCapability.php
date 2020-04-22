<?php

namespace App\Support\Traits\Relationships;

trait BelongsToCapability
{
    /**
     * Belongs to Capability.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function capability()
    {
        return $this->belongsTo('App\Models\Capability');
    }
}
