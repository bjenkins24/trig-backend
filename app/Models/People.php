<?php

namespace App\Models;

use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;

class People extends Model
{
    use PermissionTypeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['email'];
}
