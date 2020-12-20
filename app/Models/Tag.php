<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToWorkspace;
use App\Support\Traits\Relationships\HasCardTags;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToWorkspace;
    use HasCardTags;

    /**
     * @var array
     */
    protected $fillable = ['tag', 'workspace_id'];
}
