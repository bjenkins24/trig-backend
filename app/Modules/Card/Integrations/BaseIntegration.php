<?php

namespace App\Modules\Card\Integrations;

use App\Models\User;
use App\Modules\OauthConnection\OauthConnectionService;
use Illuminate\Support\Str;
use ReflectionClass;

abstract class BaseIntegration
{
    /**
     * Client object.
     *
     * @var object
     */
    protected $client;

    /**
     * User.
     *
     * @var User
     */
    protected User $user;

    /**
     * Establish the connection and return the client from the connection.
     */
    public function __construct(User $user, OauthConnectionService $oauthConnection)
    {
        $integrationKey = Str::snake((new ReflectionClass(get_class($this)))->getShortName());
        $this->client = $oauthConnection->getClient($user, $integrationKey);
        $this->user = $user;
    }
}
