<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use BelongsToManyUsers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
