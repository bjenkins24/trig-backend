<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCardCardDuplicate;
use Illuminate\Database\Eloquent\Model;

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
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereDuplicateCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate wherePrimaryCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardDuplicate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CardDuplicate extends Model
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
