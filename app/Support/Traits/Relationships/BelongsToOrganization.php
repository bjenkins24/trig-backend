<?php

namespace App\Support\Traits\Relationships;

trait BelongsToOrganization
{
    /**
     * Belongs to Organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organization()
    {
        return $this->belongsTo('App\Models\Organization');
    }
}
