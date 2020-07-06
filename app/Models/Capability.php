<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;
use App\Support\Traits\Relationships\HasPermissions;

class Capability extends BaseModel
{
    use HasPermissions;
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name'];
}
