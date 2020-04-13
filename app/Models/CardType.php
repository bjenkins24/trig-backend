<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasCards;
use Illuminate\Database\Eloquent\Model;

class CardType extends Model
{
    use HasCards;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];
}
