<?php

namespace App\Support\Traits\Relationships;

use App\Models\CollectionHiddenTag;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCollectionHiddenTags
{
    /**
     * Has many cards.
     */
    public function collectionHiddenTags(): HasMany
    {
        return $this->hasMany(CollectionHiddenTag::class);
    }
}
