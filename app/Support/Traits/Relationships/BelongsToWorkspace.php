<?php

namespace App\Support\Traits\Relationships;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToWorkspace
{
    /**
     * Belongs to Workspace.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
