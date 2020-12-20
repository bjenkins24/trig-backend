<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyTeams;
use App\Support\Traits\Relationships\BelongsToManyWorkspaces;
use App\Support\Traits\Relationships\HasCardFavorite;
use App\Support\Traits\Relationships\HasCards;
use App\Support\Traits\Relationships\HasOauthConnections;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

/**
 * App\Models\User.
 *
 * @property int                                                                                                       $id
 * @property string|null                                                                                               $first_name
 * @property string|null                                                                                               $last_name
 * @property string                                                                                                    $email
 * @property \Illuminate\Support\Carbon|null                                                                           $email_verified_at
 * @property string                                                                                                    $password
 * @property string|null                                                                                               $remember_token
 * @property \Illuminate\Support\Carbon|null                                                                           $terms_of_service_accepted_at
 * @property \Illuminate\Support\Collection|null                                                                       $properties
 * @property \Illuminate\Support\Carbon|null                                                                           $created_at
 * @property \Illuminate\Support\Carbon|null                                                                           $updated_at
 * @property \App\Models\CardFavorite|null                                                                             $cardFavorite
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Card[]                                               $cards
 * @property int|null                                                                                                  $cards_count
 * @property \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Client[]                                       $clients
 * @property int|null                                                                                                  $clients_count
 * @property \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property int|null                                                                                                  $notifications_count
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\OauthConnection[]                                    $oauthConnections
 * @property int|null                                                                                                  $oauth_connections_count
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Workspace[]                                          $workspaces
 * @property int|null                                                                                                  $workspaces_count
 * @property \App\Models\PermissionType|null                                                                           $permissionType
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Team[]                                               $teams
 * @property int|null                                                                                                  $teams_count
 * @property \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Token[]                                        $tokens
 * @property int|null                                                                                                  $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereTermsOfServiceAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use BelongsToManyWorkspaces;
    use Notifiable;
    use HasApiTokens;
    use HasOauthConnections;
    use HasCards;
    use BelongsToManyTeams;
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
        'email_verified_at'            => 'datetime',
        'properties'                   => 'collection',
        'terms_of_service_accepted_at' => 'datetime',
    ];
}
