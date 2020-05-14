<?php

namespace App\Support\Traits\Relationships;

trait BelongsToPermission
{
    /**
     * Belongs to Permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function permission()
    {
        return $this->belongsTo('App\Models\Permission');
    }
}
