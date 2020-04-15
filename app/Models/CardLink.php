<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use Illuminate\Database\Eloquent\Model;

class CardLink extends Model
{
    use BelongsToCard;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'link',
    ];
}
