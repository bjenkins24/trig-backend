<?php

namespace App\Models;

use App\Support\Traits\Relationships\PermissionTypeable;

class Person extends BaseModel
{
    use PermissionTypeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['email'];
}
