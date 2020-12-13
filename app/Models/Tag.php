<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToOrganization;
use App\Support\Traits\Relationships\HasCardTags;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToOrganization;
    use HasCardTags;

    /**
     * @var array
     */
    protected $fillable = ['tag', 'organization_id'];
}
