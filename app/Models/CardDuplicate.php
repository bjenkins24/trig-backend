<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardCardDuplicate;

class CardDuplicate extends BaseModel
{
    use BelongsToCardCardDuplicate;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'primary_card_id',
        'duplicate_card_id',
    ];
}
