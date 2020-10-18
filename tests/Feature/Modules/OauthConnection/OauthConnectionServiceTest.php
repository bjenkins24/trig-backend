<?php

namespace Tests\Feature\Modules\OauthConnection;

use App\Models\User;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleConnection;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class OauthConnectionServiceTest extends TestCase
{
    use CreateOauthConnection;

    /**
     * Get access token.
     *
     * @throws OauthIntegrationNotFound|OauthUnauthorizedRequest
     */
    public function testGetAccessToken(): void
    {
        $this->refreshDb();
        $user = User::find(1);
        $this->createOauthConnection($user);
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, 'google');
        self::assertEquals($accessToken, self::$ACCESS_TOKEN);
        $this->refreshDb();
    }

    /**
     * If we haven't been oauthed for this service it should throw an exception.
     *
     * @throws OauthIntegrationNotFound
     */
    public function testGetAccessTokenNotAuthenticated(): void
    {
        $this->expectException(OauthUnauthorizedRequest::class);
        $user = User::find(1);
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, 'google');
    }

    /**
     * Get access token from refresh token.
     *
     * @throws OauthIntegrationNotFound|OauthUnauthorizedRequest
     */
    public function testGetAccessTokenFromRefresh(): void
    {
        $accessToken = '123';
        $refreshToken = '456';
        $userId = 1;
        $this->partialMock(GoogleConnection::class, static function ($mock) use ($accessToken, $refreshToken) {
            $mock->shouldReceive('retrieveAccessTokenWithRefreshToken')
                ->andReturn(collect([
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in'    => 3600,
                ]))
                ->once();
        });

        $user = User::find($userId);
        $this->createOauthConnection($user, 0);
        sleep(1); // let's make sure the access token is expired
        $newAccessToken = app(OauthConnectionService::class)->getAccessToken($user, 'google');

        $this->assertDatabaseHas('oauth_connections', [
            'user_id'       => $userId,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ]);

        self::assertEquals($newAccessToken, $accessToken);
    }
}
