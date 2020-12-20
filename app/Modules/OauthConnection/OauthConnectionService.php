<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
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
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     * @throws OauthMissingTokens
     */
    public function getAccessToken(User $user, Workspace $workspace, string $integration): string
    {
        $integrationInstance = $this->oauthIntegrationService->makeConnectionIntegration($integration);
        $oauthConnection = $this->oauthConnectionRepo->findUserConnection($user, $workspace, $integration);

        if (! $oauthConnection) {
            throw new OauthUnauthorizedRequest('A '.$integration.' integration has not been authorized for user '.$user->id.'. Cannot get client for user.');
        }

        $accessToken = $oauthConnection->access_token;

        if ($this->oauthConnectionRepo->isExpired($oauthConnection)) {
            $authConnection = $integrationInstance->retrieveAccessTokenWithRefreshToken($oauthConnection->refresh_token);
            $this->oauthConnectionRepo->create($user, $workspace, $integration, $authConnection);
            $accessToken = $authConnection->get('access_token');
        }

        return $accessToken;
    }

    /**
     * Get a client (an authenticated integration service).
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     *
     * @return mixed
     */
    public function getClient(User $user, Workspace $workspace, string $integration)
    {
        $accessToken = $this->getAccessToken($user, $workspace, $integration);
        $integrationInstance = $this->oauthIntegrationService->makeConnectionIntegration($integration);

        return $integrationInstance->getClient($accessToken);
    }

    /**
     * Get the access token and store the connection.
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     */
    public function createConnection(User $user, Workspace $workspace, string $integration, string $authToken): OauthConnection
    {
        $authConnection = $this->getAccessTokenWithCode($integration, $authToken);

        return $this->oauthConnectionRepo->create($user, $workspace, $integration, $authConnection);
    }

    /**
     * Get the access token from an oauth request.
     *
     * @throws OauthIntegrationNotFound
     */
    public function getAccessTokenWithCode(string $integration, string $authToken): Collection
    {
        $integration = $this->oauthIntegrationService->makeConnectionIntegration($integration);

        return $integration->retrieveAccessTokenWithCode($authToken);
    }
}
