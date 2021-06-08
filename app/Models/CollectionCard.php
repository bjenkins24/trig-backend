<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\BelongsToCollection;
use Illuminate\Database\Eloquent\Model;

class CollectionCard extends Model
{
    use BelongsToCard;
    use BelongsToCollection;

    /**
     * @var string[]
     */
    protected $touches = ['card'];

    /**
     * @var array
     */
    protected $fillable = ['card_id', 'collection_id'];
}
