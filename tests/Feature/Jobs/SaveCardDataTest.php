<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Modules\Card\Integrations\GoogleIntegration;
use Tests\TestCase;

class SaveCardDataTest extends TestCase
{
    public function testSaveCardData()
    {
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('saveCardData')->once();
        });

        $syncCards = new SaveCardData(Card::find(1), 'google');
        $syncCards->handle();
    }
}
