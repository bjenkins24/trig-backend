<?php

namespace App\Modules\OauthConnection\Interfaces;

use Illuminate\Support\Collection;

interface OauthConnectionInterface
{
    public function retrieveAccessTokenWithCode(string $oauthToken): Collection;

    public function retrieveAccessTokenWithRefreshToken(string $refreshToken): Collection;

    public function getClient(string $accessToken);
}
