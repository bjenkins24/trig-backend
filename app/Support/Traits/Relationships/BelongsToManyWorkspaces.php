<?php

namespace App\Support\Traits\Relationships;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyWorkspaces
{
    /**
     * Many to many relationship with workspaces.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class);
    }
}
