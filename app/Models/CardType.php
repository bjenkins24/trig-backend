<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasCards;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CardType.
 *
 * @property int                                                         $id
 * @property string|null                                                 $name
 * @property \Illuminate\Support\Carbon|null                             $created_at
 * @property \Illuminate\Support\Carbon|null                             $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Card[] $cards
 * @property int|null                                                    $cards_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CardType extends Model
{
    use HasCards;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
