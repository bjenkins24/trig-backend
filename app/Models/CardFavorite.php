<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\BelongsToUser;

/**
 * App\Models\CardFavorite.
 *
 * @property int                             $id
 * @property int                             $card_id
 * @property int                             $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Models\Card                $card
 * @property \App\Models\User                $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardFavorite newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardFavorite newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardFavorite query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardFavorite whereCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardFavorite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardFavorite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardFavorite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardFavorite whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class CardFavorite extends BaseModel
{
    use BelongsToCard;
    use BelongsToUser;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'user_id',
    ];
}
