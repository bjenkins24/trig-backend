<?php

namespace App\Support\Traits\Relationships;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTag
{
    /**
     * Belongs to Card.
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
