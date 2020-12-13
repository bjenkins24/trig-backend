<?php

namespace Tests\Feature\Modules\CardSync;

use App\Models\CardSync;
use App\Modules\CardSync\CardSyncRepository;
use Illuminate\Support\Carbon;
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

    /**
     * Test getting last attempt.
     */
    public function testGetLastAttempt(): void
    {
        $this->refreshDb();
        $cardId = 1;
        app(CardSyncRepository::class)->create([
            'card_id' => $cardId,
            'status'  => 1,
        ]);
        sleep(1);
        app(CardSyncRepository::class)->create([
            'card_id' => $cardId,
            'status'  => 0,
        ]);

        $lastAttempt = app(CardSyncRepository::class)->getLastAttempt($cardId);
        self::assertEquals(0, $lastAttempt->status);
    }

    public function testSecondsSinceLastAttempt(): void
    {
        $this->refreshDb();
        $cardId = 1;
        $cardSyncRepository = app(CardSyncRepository::class);
        $testCreate = '2020-11-20 00:00:00';
        Carbon::setTestNow('2020-11-20 00:00:20');
        // We need to use insert instead of create so we can write to created_at without fillable on the model
        CardSync::insert([
            'card_id'    => $cardId,
            'status'     => 1,
            'created_at' => $testCreate,
        ]);
        $result = $cardSyncRepository->secondsSinceLastAttempt($cardId);
        self::assertEquals(20, $result);

        $result = $cardSyncRepository->secondsSinceLastAttempt(2000);
        self::assertNull($result);
    }

    public function testShouldGetTags(): void
    {
        $cardSyncRepository = app(CardSyncRepository::class);
        $cardSyncRepository->shouldGetTags();
    }
}
