<?php

namespace App\Support\Traits\Relationships;

use App\Models\CardView;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCardView
{
    /**
     * Has one card favorite.
     */
    public function cardView(): HasOne
    {
        return $this->hasOne(CardView::class);
    }
}
