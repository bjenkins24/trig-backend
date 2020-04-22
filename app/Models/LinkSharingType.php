<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkSharingSettings;
use Illuminate\Database\Eloquent\Model;

class LinkSharingType extends Model
{
    use HasLinkSharingSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
