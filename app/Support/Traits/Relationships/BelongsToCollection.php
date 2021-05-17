<?php

namespace App\Support\Traits\Relationships;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCollection
{
    /**
     * Belongs to Card.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
