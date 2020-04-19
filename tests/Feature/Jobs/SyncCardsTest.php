<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class SyncCardsTest extends TestCase
{
    use RefreshDatabase;
    use CreateOauthConnection;

    /**
     * Test sync cards job.
     *
     * @return void
     */
    public function testSyncCards()
    {
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('syncCards')->once();
        });

        $syncCards = new SyncCards(User::find(1), 'google');
        $syncCards->handle();
    }
}
