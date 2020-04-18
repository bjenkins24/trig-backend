<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use App\Modules\OauthConnection\Repositories\StoreConnection;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OauthConnectionService
{
    /**
     * Create connection repository.
     *
     * @var StoreConnection repository
     */
    private StoreConnection $store;

    /**
     * @var OauthIntegrationService
     */
    private OauthIntegrationService $oauthIntegration;

    /**
     * Create instance of create connection service.
     */
    public function __construct(StoreConnection $store, OauthIntegrationService $oauthIntegration)
    {
        $this->store = $store;
        $this->oauthIntegration = $oauthIntegration;
    }

    public function makeIntegration(string $integration): OauthConnectionInterface
    {
        return $this->oauthIntegration->makeIntegration('App\\Modules\\OauthConnection\\Connections', $integration);
    }

    public function getAccessToken(User $user, string $integration)
    {
        $integrationInstance = $this->makeIntegration($integration);
        $oauthIntegration = OauthIntegration::where('name', $integration)->first();
        $oauthConnection = $oauthIntegration->oauthConnections()->where('user_id', $user->id)->first();

        $accessToken = $oauthConnection->access_token;

        if (! $oauthConnection) {
            throw new OauthUnauthorizedRequest('A '.$integration.' has not been authorized for user '.$user->id.'. Cannot get client for user.');
        }

        if ($oauthConnection->expires->isBefore(Carbon::now())) {
            $authConnection = $integrationInstance->retrieveAccessTokenWithRefreshToken($oauthConnection->refresh_token);
            $this->store->handle($user, $integration, $authConnection);
            $accessToken = $authConnection->get('access_token');
        }

        return $accessToken;
    }

    public function getClient(User $user, string $integration)
    {
        $accessToken = $this->getAccessToken($user, $integration);
        $integrationInstance = $this->makeIntegration($integration);

        return $integrationInstance->getClient($accessToken);
    }

    /**
     * Get the access token and store the connection.
     *
     * @return User
     */
    public function createConnection(User $user, string $integration, string $authToken)
    {
        $authConnection = $this->getAccessTokenWithCode($integration, $authToken);

        return $this->storeConnection($user, $integration, $authConnection);
    }

    /**
     * Get the access token from an oauth request.
     */
    public function getAccessTokenWithCode(string $integration, string $authToken): Collection
    {
        $integration = $this->makeIntegration($integration);

        return $integration->retrieveAccessTokenWithCode($authToken);
    }

    /**
     * Store the connection.
     *
     * @return void
     */
    public function storeConnection(User $user, string $integration, Collection $authConnection)
    {
        return $this->store->handle($user, $integration, $authConnection);
    }
}
