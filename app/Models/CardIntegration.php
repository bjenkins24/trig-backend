<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\BelongsToOauthIntegration;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CardIntegration.
 *
 * @property int                             $id
 * @property int                             $card_id
 * @property int                             $oauth_integration_id
 * @property string                          $foreign_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Models\Card                $card
 * @property \App\Models\OauthIntegration    $oauthIntegration
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereForeignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereOauthIntegrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CardIntegration whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CardIntegration extends Model
{
    use BelongsToOauthIntegration;
    use BelongsToCard;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'oauth_integration_id',
        'foreign_id',
    ];
}
