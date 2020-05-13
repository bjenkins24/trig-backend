<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Support\Collection;

class OauthConnectionService
{
    private OauthConnectionRepository $oauthConnectionRepo;
    private OauthIntegrationService $oauthIntegrationService;

    /**
     * Create instance of create connection service.
     */
    public function __construct(
        OauthConnectionRepository $oauthConnectionRepo,
        OauthIntegrationService $oauthIntegrationService
    ) {
        $this->oauthConnectionRepo = $oauthConnectionRepo;
        $this->oauthIntegrationService = $oauthIntegrationService;
    }

    /**
     * Get an access token either from the DB or from a refresh token.
     */
    public function getAccessToken(User $user, string $integration): string
    {
        $integrationInstance = $this->oauthIntegrationService->makeConnectionIntegration($integration);
        $oauthConnection = $this->oauthConnectionRepo->findUserConnection($user, $integration);

        if (! $oauthConnection) {
            throw new OauthUnauthorizedRequest('A '.$integration.' has not been authorized for user '.$user->id.'. Cannot get client for user.');
        }

        $accessToken = $oauthConnection->access_token;

        if ($this->oauthConnectionRepo->isExpired($oauthConnection)) {
            $authConnection = $integrationInstance->retrieveAccessTokenWithRefreshToken($oauthConnection->refresh_token);
            $this->oauthConnectionRepo->create($user, $integration, $authConnection);
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
        $integrationInstance = $this->oauthIntegrationService->makeConnectionIntegration($integration);

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

        return $this->oauthConnectionRepo->create($user, $integration, $authConnection);
    }

    /**
     * Get the access token from an oauth request.
     */
    public function getAccessTokenWithCode(string $integration, string $authToken): Collection
    {
        $integration = $this->oauthIntegrationService->makeConnectionIntegration($integration);

        return $integration->retrieveAccessTokenWithCode($authToken);
    }
}
