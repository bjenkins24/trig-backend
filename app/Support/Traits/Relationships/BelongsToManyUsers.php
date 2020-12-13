<?php

namespace App\Support\Traits\Relationships;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyUsers
{
    /**
     * Many to many relationship with users.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
