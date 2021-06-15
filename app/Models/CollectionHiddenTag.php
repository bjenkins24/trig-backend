<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCollection;
use Illuminate\Database\Eloquent\Model;

class CollectionHiddenTag extends Model
{
    use BelongsToCollection;

    /**
     * @var array
     */
    protected $fillable = ['collection_id', 'tag'];
}
