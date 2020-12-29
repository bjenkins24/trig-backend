<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToWorkspace;
use App\Support\Traits\Relationships\HasCardTags;
use ElasticScoutDriverPlus\CustomSearch;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Tag extends Model
{
    use BelongsToWorkspace;
    use HasCardTags;
    use CustomSearch;
    use Searchable;

    /**
     * @var array
     */
    protected $fillable = ['tag', 'workspace_id'];
}
