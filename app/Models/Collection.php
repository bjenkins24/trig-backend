<?php

namespace App\Models;

use App\Support\Traits\HandlesProperties;
use App\Support\Traits\Relationships\BelongsToUser;
use App\Support\Traits\Relationships\HasCollectionCards;
use App\Support\Traits\Relationships\Permissionables;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use BelongsToUser;
    use HandlesProperties;
    use HasCollectionCards;
    use Permissionables;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'token',
        'properties',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'collection',
    ];
}
