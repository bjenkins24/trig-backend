<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToLinkShareType
{
    /**
     * Belongs to LinkShareType.
     */
    public function linkShareType(): BelongsTo
    {
        return $this->belongsTo('App\Models\LinkShareType');
    }
}
