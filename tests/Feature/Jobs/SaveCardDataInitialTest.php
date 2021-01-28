<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveCardDataInitial;
use App\Models\Card;
use App\Modules\Card\Integrations\SyncCards;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveCardDataInitialTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveCardDataInitial(): void
    {
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveInitialCardData')->once();
        });

        $syncCards = new SaveCardDataInitial(Card::find(1), 'link');
        $result = $syncCards->handle();
        self::assertTrue($result);
    }

    public function testSaveCardDataFailedSync(): void
    {
        $this->partialMock(SyncCards::class, static function ($mock) {
            $mock->shouldReceive('saveInitialCardData')->andThrow(new Exception());
        });

        $syncCards = new SaveCardDataInitial(Card::find(1), 'google');
        $result = $syncCards->handle();
        self::assertFalse($result);
    }
}
