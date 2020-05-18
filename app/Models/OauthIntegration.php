<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasCardIntegration;
use App\Support\Traits\Relationships\HasOauthConnections;

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
