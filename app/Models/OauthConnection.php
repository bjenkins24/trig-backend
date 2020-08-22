<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToOauthIntegration;
use App\Support\Traits\Relationships\BelongsToUser;

/**
 * App\Models\OauthConnection.
 *
 * @property int                                 $id
 * @property int                                 $user_id
 * @property int                                 $oauth_integration_id
 * @property string|null                         $access_token
 * @property string|null                         $refresh_token
 * @property \Illuminate\Support\Carbon|null     $expires
 * @property \Illuminate\Support\Collection|null $properties
 * @property \Illuminate\Support\Carbon|null     $created_at
 * @property \Illuminate\Support\Carbon|null     $updated_at
 * @property \App\Models\OauthIntegration        $oauthIntegration
 * @property \App\Models\User                    $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthConnection newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthConnection newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\OauthConnection query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereExpires($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereOauthIntegrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OauthConnection whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class OauthConnection extends BaseModel
{
    use BelongsToUser;
    use BelongsToOauthIntegration;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'oauth_integration_id',
        'access_token',
        'refresh_token',
        'expires',
        'properties',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expires'    => 'datetime',
        'properties' => 'collection',
    ];
}
