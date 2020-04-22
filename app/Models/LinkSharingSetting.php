<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToLinkSharingType;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;

class LinkSharingSetting extends Model
{
    use PermissionTypeable;
    use BelongsToLinkSharingType;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['link_sharing_type_id'];
}
