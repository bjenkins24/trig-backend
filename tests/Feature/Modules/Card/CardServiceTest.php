<?php

namespace Tests\Feature\Modules\Card;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\Card\CardService;
use App\Modules\OauthConnection\OauthConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        app(OauthConnectionService::class)->storeConnection($user, 'google', collect([
            'access_token'  => '123',
            'refresh_token' => '456',
            'expires_in'    => 0,
        ]));

        return $user;
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncAll()
    {
        Queue::fake();

        $user = $this->createOauthConnection();

        app(CardService::class)->syncAllIntegrations($user);

        Queue::assertPushed(SyncCards::class, 1);
    }
}
