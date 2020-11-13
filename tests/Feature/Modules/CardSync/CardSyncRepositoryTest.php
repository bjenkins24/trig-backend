<?php

namespace Tests\Feature\Modules\CardSync;

use App\Modules\CardSync\CardSyncRepository;
use Tests\TestCase;

class CardSyncRepositoryTest extends TestCase
{
    /**
     * Test syncing all integrations.
     */
    public function testCreate(): void
    {
        app(CardSyncRepository::class)->create([
            'card_id' => 1,
            'status'  => 1,
        ]);
        $this->assertDatabaseHas('card_syncs', [
            'card_id' => 1,
            'status'  => 1,
        ]);
    }
}
