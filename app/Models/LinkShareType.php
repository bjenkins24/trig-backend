<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;
use Illuminate\Database\Eloquent\Model;

class LinkShareType extends Model
{
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
