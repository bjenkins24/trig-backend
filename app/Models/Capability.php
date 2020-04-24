<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    use HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
