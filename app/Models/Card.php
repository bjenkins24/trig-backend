<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardType;
use App\Support\Traits\Relationships\BelongsToUser;
use App\Support\Traits\Relationships\HasCardLink;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use BelongsToUser;
    use BelongsToCardType;
    use HasCardLink;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'card_type_id',
        'title',
        'description',
        'image',
        'actual_created_at',
        'actual_modified_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
         'actual_created_at',
        'actual_modified_at',
    ];
}
