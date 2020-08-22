<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasCardIntegration;
use App\Support\Traits\Relationships\HasOauthConnections;

/**
 * App\Models\OauthIntegration.
 *
 * @property int                                                                    $id
 * @property string                                                                 $name
 * @property \Illuminate\Support\Carbon|null                                        $created_at
 * @property \Illuminate\Support\Carbon|null                                        $updated_at
 * @property \App\Models\CardIntegration|null                                       $cardIntegration
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\OauthConnection[] $oauthConnections
 * @property int|null                                                               $oauth_connections_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthIntegration newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthIntegration newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthIntegration query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthIntegration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthIntegration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthIntegration whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthIntegration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class OauthIntegration extends BaseModel
{
    use HasOauthConnections;
    use HasCardIntegration;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
