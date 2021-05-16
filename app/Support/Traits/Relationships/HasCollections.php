<?php

namespace App\Support\Traits\Relationships;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCollections
{
    /**
     * Has many collections.
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
