<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkSharingSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['link_sharing_type_id'];
}
