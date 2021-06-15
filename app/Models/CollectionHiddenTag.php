<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCollection;
use App\Support\Traits\Relationships\BelongsToTag;
use Illuminate\Database\Eloquent\Model;

class CollectionHiddenTag extends Model
{
    use BelongsToCollection;
    use BelongsToTag;

    /**
     * @var array
     */
    protected $fillable = ['collection_id', 'tag'];
}
