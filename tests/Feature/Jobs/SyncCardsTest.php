<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncCards;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Tests\TestCase;

class SyncCardsTest extends TestCase
{
    /**
     * Test sync cards job.
     *
     * @throws CardIntegrationCreationValidate
     * @throws OauthIntegrationNotFound
     */
    public function testSyncCards(): void
    {
        $this->partialMock(SyncCardsIntegration::class, static function ($mock) {
            $mock->shouldReceive('syncCards')->once();
        });

        $syncCards = new SyncCards(1, 1, 'google');
        $syncCards->handle();
    }
}
