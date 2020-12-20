<?php

namespace App\Support\Traits\Relationships;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTags
{
    /**
     * Has many cards.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
