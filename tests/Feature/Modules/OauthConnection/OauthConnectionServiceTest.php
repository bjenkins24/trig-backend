<?php

namespace Tests\Feature\Modules\OauthConnection;

use App\Models\User;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\OauthConnectionService;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class OauthConnectionServiceTest extends TestCase
{
    use CreateOauthConnection;

    /**
     * Get access token.
     *
     * @return void
     */
    public function testGetAccessToken()
    {
        $this->refreshDb();
        $user = User::find(1);
        $this->createOauthConnection($user);
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, 'google');
        $this->assertEquals($accessToken, self::$ACCESS_TOKEN);
        $this->refreshDb();
    }

    /**
     * If we haven't been oauthed for this service it should throw an exception.
     *
     * @return void
     */
    public function testGetAccessTokenNotAuthenticated()
    {
        $this->expectException(OauthUnauthorizedRequest::class);
        $user = User::find(1);
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, 'google');
    }

    /**
     * Get access token from refresh token.
     *
     * @return void
     */
    public function testGetAccessTokenFromRefresh()
    {
        $accessToken = '123';
        $refreshToken = '456';
        $userId = 1;
        $this->partialMock(GoogleConnection::class, function ($mock) use ($accessToken, $refreshToken) {
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

        $this->assertEquals($newAccessToken, $accessToken);
    }
}
