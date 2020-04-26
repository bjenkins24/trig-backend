<?php

namespace App\Support\Traits\Relationships;

trait LinkShareable
{
    /**
     * Get the owning link share setting     model.
     */
    public function linkShareSetting()
    {
        return $this->morphOne('App\Models\LinkShareSetting', 'shareable');
    }
}
