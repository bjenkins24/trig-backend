<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;

class LinkShareType extends BaseModel
{
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
