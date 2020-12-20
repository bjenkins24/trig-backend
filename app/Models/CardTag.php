<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\BelongsToTag;
use Illuminate\Database\Eloquent\Model;

class CardTag extends Model
{
    use BelongsToCard;
    use BelongsToTag;

    /**
     * @var array
     */
    protected $fillable = ['card_id', 'tag_id'];
}
