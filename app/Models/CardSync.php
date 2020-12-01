<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use Illuminate\Database\Eloquent\Model;

class CardSync extends Model
{
    use BelongsToCard;

    /**
     * The attributes that are mass assignable.
     * status = 0 - Failed
     * status = 1 - Success
     * status = 2 - Fail hard - do not retry (like 404 website).
     *
     * @var array
     */
    protected $fillable = ['status', 'card_id'];
}
