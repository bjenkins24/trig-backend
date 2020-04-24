<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    use HasPermissions;

    const WRITER_ID = 1;
    const READER_ID = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
