<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;
use App\Support\Traits\Relationships\BelongsToOauthIntegration;

class CardIntegration extends BaseModel
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
