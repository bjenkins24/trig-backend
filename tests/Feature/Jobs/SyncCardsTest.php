<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncCards;
use App\Modules\Card\Integrations\GoogleIntegration;
use Tests\TestCase;

class SyncCardsTest extends TestCase
{
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

        $syncCards = new SyncCards(1, 'google');
        $syncCards->handle();
    }
}
