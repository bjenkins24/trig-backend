<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToOauthIntegration;
use App\Support\Traits\Relationships\BelongsToUser;

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
