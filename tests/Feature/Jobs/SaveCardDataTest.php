<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Modules\Card\Integrations\SyncCards;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Tests\TestCase;

class SaveCardDataTest extends TestCase
{
    /**
     * @throws OauthIntegrationNotFound
     */
    public function testSaveCardData(): void
    {
        $this->refreshDb();
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveCardData')->once();
        });

        $syncCards = new SaveCardData(Card::find(1), 'google');
        $syncCards->handle();
    }
}
