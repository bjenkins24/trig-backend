<?php

namespace App\Support\Traits\Relationships;

trait HasCardLink
{
    /**
     * Has one card links.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cardLink()
    {
        return $this->hasOne('App\Models\CardLink');
    }
}
