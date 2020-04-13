<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToOauthIntegration;
use App\Support\Traits\Relationships\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class OauthConnection extends Model
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
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expires' => 'date',
    ];
}
