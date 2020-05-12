<?php

namespace Tests\Feature\Modules\Card;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\Card\CardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class CardServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreateOauthConnection;

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncAll()
    {
        \Queue::fake();

        $user = User::find(1);
        $this->createOauthConnection($user);

        app(CardService::class)->syncAllIntegrations($user);

        \Queue::assertPushed(SyncCards::class, 1);
    }
}
