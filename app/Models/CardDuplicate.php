<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardCardDuplicate;

class CardDuplicate extends BaseModel
{
    use BelongsToCardCardDuplicate;

    /**
     * Make sure we update the card when card duplicates are created. Which will
     * update the elastic search index.
     *
     * @var array
     */
    protected $touches = ['card', 'primaryCard'];

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
