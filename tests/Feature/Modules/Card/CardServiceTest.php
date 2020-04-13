<?php

namespace Tests\Feature\Models;

use App\Models\User;
use App\Modules\Card\CardService;
use App\Modules\Card\Integrations\Google;
use App\Modules\OauthConnection\OauthConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create fake oauth connection for testing.
     *
     * @return void
     */
    private function createOauthConnection()
    {
        $user = User::find(1);

        app(OauthConnectionService::class)->storeConnection(
            $user,
            'google',
            collect(['access_token' => '123', 'refresh_token' => '456', 'expires_in' => 3000])
        );

        return $user;
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     * @group n
     */
    public function testSyncAll()
    {
        $user = $this->createOauthConnection();
        $this->partialMock(OauthConnectionService::class, function ($mock) {
            $mock->shouldReceive('getClient')->once();
        });

        $this->mock(Google::class, function ($mock) {
            $mock->shouldReceive('syncCards')->once();
        });

        app(CardService::class)->syncAllIntegrations($user);
    }
}
