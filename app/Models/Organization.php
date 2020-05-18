<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;

class Organization extends BaseModel
{
    use BelongsToManyUsers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
