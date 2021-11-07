<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\HasCardTweetLink;
use App\Support\Traits\Relationships\HasCardTweetReply;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTweet extends Model
{
    use HasFactory;
    use BelongsToCard;
    use HasCardTweetLink;
    use HasCardTweetReply;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'name',
        'handle',
        'avatar',
        'created_at',
        'updated_at',
    ];
}
