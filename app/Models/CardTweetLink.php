<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardTweet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTweetLink extends Model
{
    use HasFactory;
    use BelongsToCardTweet;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_tweet_id',
        'href',
        'image_src',
        'url',
        'title',
        'description',
        'created_at',
        'updated_at',
    ];
}
