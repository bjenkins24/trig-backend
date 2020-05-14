<?php

namespace App\Modules\OauthConnection\Connections;

use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use Google_Client as GoogleClient;
use Illuminate\Support\Collection;

class GoogleConnection implements OauthConnectionInterface
{
    /**
     * Google client class.
     *
     * @var GoogleClient client
     */
    private GoogleClient $client;

    /**
     * Initiate basic client steps.
     */
    public function __construct(GoogleClient $googleClient)
    {
        $googleClient->setApplicationName('Trig');
        $googleClient->setClientId(\Config::get('services.google.client_id'));
        $googleClient->setClientSecret(\Config::get('services.google.client_secret'));
        $googleClient->setAccessType('offline');
        $googleClient->setPrompt('select_account consent');
        $googleClient->setDeveloperKey(\Config::get('services.google.drive_api_key'));
        $googleClient->setRedirectUri('http://localhost:8080');
        $this->client = $googleClient;
    }

    public static function getKey(): string
    {
        return 'google';
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
        $payload = $this->client->verifyIdToken($oauthCredentials->get('id_token'));
        if (! $payload) {
            return [];
        }

        return [
            'payload'          => collect($payload),
            'oauthCredentials' => $oauthCredentials,
        ];
    }
}
