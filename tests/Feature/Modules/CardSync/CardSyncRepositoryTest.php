<?php

namespace Tests\Feature\Modules\CardSync;

use App\Models\Card;
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
        $this->refreshDb();
    }

    public function testShouldGetTags(): void
    {
        $cardSyncRepository = app(CardSyncRepository::class);
        $card = Card::find(1);

        // Hasn't tried to sync yet = yes
        $shouldGetTags = $cardSyncRepository->shouldGetTags($card, $card->content);
        self::assertTrue($shouldGetTags);

        $card->cardSync()->create([
            'status'  => 1,
            'card_id' => $card->id,
        ]);

        $card->content = '';
        $card->save();

        // No content don't get tags
        $shouldGetTags = $cardSyncRepository->shouldGetTags($card, $card->content);
        self::assertFalse($shouldGetTags);

        $card->content = 'My first cool thing that I\'m doing today is to learn about SaaS';
        $card->save();

        // Completely different content get tags again
        $shouldGetTags = $cardSyncRepository->shouldGetTags($card, 'Completely different content');
        self::assertTrue($shouldGetTags);

        // Almost the same content don't get tags - this is based off str length because a fuzzy dedupe is too
        // much work for the lift here.
        $shouldGetTags = $cardSyncRepository->shouldGetTags($card, $card->content.' that\'s it');
        self::assertFalse($shouldGetTags);

        $this->refreshDb();
    }
}
