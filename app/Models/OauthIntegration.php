<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasOauthConnections;
use Illuminate\Database\Eloquent\Model;

class OauthIntegration extends Model
{
    use HasOauthConnections;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
