<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyOrganizations;
use App\Support\Traits\Relationships\HasCardFavorite;
use App\Support\Traits\Relationships\HasCards;
use App\Support\Traits\Relationships\HasOauthConnections;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToManyOrganizations;
    use Notifiable;
    use HasApiTokens;
    use HasOauthConnections;
    use HasCards;
    use HasTeams;
    use HasCardFavorite;
    use PermissionTypeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'terms_of_service_accepted_at',
        'properties',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'terms_of_service_accepted_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'properties'        => 'array',
    ];
}
