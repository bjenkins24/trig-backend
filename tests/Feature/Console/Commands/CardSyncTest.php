<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\CardSync;
use App\Jobs\SyncCards;
use App\Models\OauthConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CardSyncTest extends TestCase
{
    use RefreshDatabase;

    public function testCardSync(): void
    {
        OauthConnection::create([
            'user_id'              => 1,
            'workspace_id'         => 1,
            'oauth_integration_id' => 1,
            'access_token'         => '123',
            'refresh_token'        => '123',
            'expires'              => '2020-06-04 06:29:39',
        ]);

        \Queue::fake();

        $syncCards = new CardSync();
        $syncCards->handle();

        Queue::assertPushed(SyncCards::class, 1);
    }
}
