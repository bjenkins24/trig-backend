<?php

namespace App\Support\Traits\Relationships;

trait BelongsToLinkShareType
{
    /**
     * Belongs to LinkShareType.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function linkShareType()
    {
        return $this->belongsTo('App\Models\LinkShareType');
    }
}
