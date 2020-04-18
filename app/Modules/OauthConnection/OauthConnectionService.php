<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Support\Collection;

class OauthConnectionService
{
    /**
     * Create connection repository.
     *
     * @var OauthConnectionRepository repository
     */
    public OauthConnectionRepository $repo;

    /**
     * @var OauthIntegrationService
     */
    private OauthIntegrationService $oauthIntegration;

    /**
     * Create instance of create connection service.
     */
    public function __construct(
        OauthConnectionRepository $repo,
        OauthIntegrationService $oauthIntegration
    ) {
        $this->repo = $repo;
        $this->oauthIntegration = $oauthIntegration;
    }

    public function makeIntegration(string $integration): OauthConnectionInterface
    {
        return $this->oauthIntegration->makeIntegration(
            'App\\Modules\\OauthConnection\\Connections',
            $integration,
            'connection'
        );
    }

    /**
     * Get an access token either from the DB or from a refresh token.
     */
    public function getAccessToken(User $user, string $integration): string
    {
        $integrationInstance = $this->makeIntegration($integration);
        $oauthConnection = $this->repo->findUserConnection($user, $integration);

        $accessToken = $oauthConnection->access_token;

        if (! $oauthConnection) {
            throw new OauthUnauthorizedRequest('A '.$integration.' has not been authorized for user '.$user->id.'. Cannot get client for user.');
        }

        if ($this->repo->isExpired($oauthConnection)) {
            $authConnection = $integrationInstance->retrieveAccessTokenWithRefreshToken($oauthConnection->refresh_token);
            $this->repo->create($user, $integration, $authConnection);
            $accessToken = $authConnection->get('access_token');
        }

        return $accessToken;
    }

    /**
     * Get a client (an authenticated integration service).
     *
     * @return void
     */
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
    public function createConnection(User $user, string $integration, string $authToken): OauthConnection
    {
        $authConnection = $this->getAccessTokenWithCode($integration, $authToken);

        return $this->repo->create($user, $integration, $authConnection);
    }

    /**
     * Get the access token from an oauth request.
     */
    public function getAccessTokenWithCode(string $integration, string $authToken): Collection
    {
        $integration = $this->makeIntegration($integration);

        return $integration->retrieveAccessTokenWithCode($authToken);
    }
}
