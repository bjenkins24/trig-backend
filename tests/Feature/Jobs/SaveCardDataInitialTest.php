<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveCardDataInitial;
use App\Modules\Card\Integrations\SyncCards;
use App\Utils\TikaWebClientWrapper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveCardDataInitialTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveCardDataInitial(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveInitialCardData')->once();
        });

        $syncCards = new SaveCardDataInitial(1, 'link');
        $result = $syncCards->handle();
        self::assertTrue($result);
    }

    public function testSaveCardDataFailedSync(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveInitialCardData')->andThrow(new Exception());
        });

        $syncCards = new SaveCardDataInitial(1, 'google');
        $result = $syncCards->handle();
        self::assertFalse($result);
    }
}
