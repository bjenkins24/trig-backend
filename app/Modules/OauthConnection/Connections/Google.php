<?php

namespace App\Modules\OauthConnection\Connections;

use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use Google_Client as GoogleClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class Google implements OauthConnectionInterface
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
    public function __construct()
    {
        $client = new GoogleClient();
        $client->setApplicationName('Trig');
        $client->setClientId(Config::get('services.google.client_id'));
        $client->setClientSecret(Config::get('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setDeveloperKey(Config::get('services.google.drive_api_key'));
        $client->setRedirectUri('http://localhost:8080');
        $this->client = $client;
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

    public function getClient(string $accessToken)
    {
        $this->client->setAccessToken($accessToken);

        return $this->client;
    }
}
