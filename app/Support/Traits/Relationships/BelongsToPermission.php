<?php

namespace App\Support\Traits\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToPermission
{
    /**
     * Belongs to Permission.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo('App\Models\Permission');
    }
}
