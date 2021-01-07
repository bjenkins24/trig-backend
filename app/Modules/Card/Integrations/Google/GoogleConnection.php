<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\User;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Interfaces\ConnectionInterface;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Google_Client as GoogleClient;
use Google_Service_Directory as GoogleServiceDirectory;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class GoogleConnection implements ConnectionInterface
{
    /**
     * Google client class.
     *
     * @var GoogleClient client
     */
    private GoogleClient $client;
    private OauthConnectionService $oauthConnectionService;

    /**
     * Initiate basic client steps.
     */
    public function __construct(GoogleClient $googleClient, OauthConnectionService $oauthConnectionService)
    {
        $this->oauthConnectionService = $oauthConnectionService;
        $googleClient->setApplicationName('Trig');
        $googleClient->setClientId(Config::get('services.google.client_id'));
        $googleClient->setClientSecret(Config::get('services.google.client_secret'));
        $googleClient->setAccessType('offline');
        $googleClient->setPrompt('select_account consent');
        $googleClient->setDeveloperKey(Config::get('services.google.drive_api_key'));
        $googleClient->setRedirectUri('http://localhost:8080');
        $googleClient->addScope(['profile', 'email']);
        $this->client = $googleClient;
    }

    public function retrieveAccessTokenWithCode(string $authToken): Collection
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authToken);

        return collect($accessToken);
    }

    public function retrieveAccessTokenWithRefreshToken(string $refreshToken): Collection
    {
        $accessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

        return collect($accessToken);
    }

    public function getClient(string $accessToken): GoogleClient
    {
        $this->client->setAccessToken($accessToken);

        return $this->client;
    }

    public function getUser(string $code): array
    {
        $oauthCredentials = $this->retrieveAccessTokenWithCode($code);
        dd($oauthCredentials);
        $payload = $this->client->verifyIdToken($oauthCredentials->get('id_token'));
        if (! $payload) {
            return [];
        }

        return [
            'payload'          => collect($payload),
            'oauthCredentials' => $oauthCredentials,
        ];
    }

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function getDriveService(User $user)
    {
        $client = $this->oauthConnectionService->getClient($user, GoogleIntegration::getIntegrationKey());

        return new GoogleServiceDrive($client);
    }

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function getDirectoryService(User $user): GoogleServiceDirectory
    {
        $client = $this->oauthConnectionService->getClient($user, GoogleIntegration::getIntegrationKey());

        return new GoogleServiceDirectory($client);
    }
}
