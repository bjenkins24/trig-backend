<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthNotFoundException;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use App\Modules\OauthConnection\Repositories\StoreConnection;
use Exception;
use Illuminate\Support\Collection;

class OauthConnectionService
{
    /**
     * Create connection repository.
     *
     * @var StoreConnection repository
     */
    protected StoreConnection $store;

    /**
     * Create instance of create connection service.
     */
    public function __construct(StoreConnection $store)
    {
        $this->store = $store;
    }

    private function makeIntegration(string $integration): OauthConnectionInterface
    {
        $path = "App\\Modules\\OauthConnection\\Connections\\$integration";
        try {
            return new $path();
        } catch (Exception $e) {
            throw new OauthNotFoundException("The integration key \"$integration\" is not valid. Please check the name and try again.");
        }
    }

    public function getClient(User $user, string $integration)
    {
        $integrationInstance = $this->makeIntegration($integration);
        $oauthConnection = new OauthConnection();
        $oauthIntegration = OauthIntegration::where('name', $integration);
        $oauthRecord = $oauthConnection
            ->where('user_id', $user->id)
            ->where('oauth_integration_id', $oauthIntegration->id)
            ->first();

        $accessToken = $oauthRecord->access_token;

        if (! $oauthRecord) {
            throw new OauthUnauthorizedRequest('A '.$integration.' has not been authorized for user '.$user->id.'. Cannot get client for user.');
        }

        if ($oauthRecord->expires->isAfter(Carbon::now())) {
            $authConnection = $integrationInstance->retrieveAccessTokenWithRefreshToken($oauthRecord->refresh_token);
            $this->store->handle($user, $integration, $authConnection);
            $accessToken = $authConnection->get('access_token');
        }

        return $integrationInstance->getClient($accessToken);
    }

    /**
     * Get the access token and store the connection.
     *
     * @return User
     */
    public function createConnection(User $user, string $integration, string $authToken)
    {
        $authConnection = $this->getAccessToken($integration, $authToken);

        return $this->storeConnection($user, $integration, $authConnection);
    }

    /**
     * Get the access token from an oauth request.
     */
    public function getAccessToken(string $integration, string $authToken): Collection
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
