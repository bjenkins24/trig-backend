<?php

namespace Tests\Feature\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\OauthIntegration;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\OauthConnection\OauthConnectionRepository;
use Tests\TestCase;

class OauthConnectionRepositoryTest extends TestCase
{
    /**
     * Get access token.
     */
    public function testMissingTokens(): void
    {
        $this->expectException(OauthMissingTokens::class);
        app(OauthConnectionRepository::class)->create(User::find(1), Organization::find(1), 'google', collect([]));
    }

    public function testGetAllActiveConnections(): void
    {
        $this->refreshDb();

        $confluenceId = OauthIntegration::create([
            'name' => 'confluence',
        ])->id;
        OauthConnection::create([
            'user_id'              => 1,
            'organization_id'      => 1,
            'oauth_integration_id' => 1,
            'access_token'         => '123',
            'refresh_token'        => '123',
            'expires'              => '2020-06-04 06:29:39',
        ]);
        OauthConnection::create([
            'user_id'              => 1,
            'organization_id'      => 1,
            'oauth_integration_id' => $confluenceId,
            'access_token'         => '123',
            'refresh_token'        => '123',
            'expires'              => '2020-06-04 06:29:39',
        ]);

        $activeConnections = app(OauthConnectionRepository::class)->getAllActiveConnections();

        self::assertEquals(collect([
            ['user_id' => 1, 'key' => 'google'],
            ['user_id' => 1, 'key' => 'confluence'],
        ]), $activeConnections);
    }
}
