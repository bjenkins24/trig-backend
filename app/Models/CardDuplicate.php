<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardCardDuplicate;

/**
 * App\Models\CardDuplicate.
 *
 * @property int                             $id
 * @property int                             $primary_card_id
 * @property int                             $duplicate_card_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Models\Card                $card
 * @property \App\Models\Card                $primaryCard
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardDuplicate newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardDuplicate newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\CardDuplicate query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereDuplicateCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate wherePrimaryCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class CardDuplicate extends BaseModel
{
    use BelongsToCardCardDuplicate;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'primary_card_id',
        'duplicate_card_id',
    ];
}
