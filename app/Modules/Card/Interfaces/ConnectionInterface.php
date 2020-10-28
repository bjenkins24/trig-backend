<?php

namespace App\Modules\Card\Interfaces;

use Illuminate\Support\Collection;

interface ConnectionInterface
{
    public function retrieveAccessTokenWithCode(string $oauthToken): Collection;

    public function retrieveAccessTokenWithRefreshToken(string $refreshToken): Collection;

    public function getClient(string $accessToken);
}
