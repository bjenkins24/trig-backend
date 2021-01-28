<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Modules\Card\Integrations\SyncCards;
use App\Modules\CardSync\CardSyncRepository;
use App\Utils\TikaWebClientWrapper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveCardDataTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveCardData(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveCardData')->once();
        });

        $syncCards = new SaveCardData(Card::find(1), 'link');
        $syncCards->handle();
    }

    public function testSaveCardDataFailedSync(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveCardData')->andThrow(new Exception());
        });

        $this->mock(CardSyncRepository::class, static function ($mock) {
            $mock->shouldReceive('create')->with(['card_id' => 1, 'status' => 0]);
        });

        $syncCards = new SaveCardData(Card::find(1), 'google');
        $syncCards->handle();
    }
}
