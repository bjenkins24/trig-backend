<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasCards;

class CardType extends BaseModel
{
    use HasCards;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
