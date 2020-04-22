<?php

namespace App\Support\Traits\Relationships;

trait BelongsToLinkSharingType
{
    /**
     * Belongs to LinkSharingType.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function linkSharingType()
    {
        return $this->belongsTo('App\Models\LinkSharingType');
    }
}
