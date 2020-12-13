<?php

namespace App\Modules\CardSync;

use App\Models\Card;
use App\Models\CardSync;
use App\Models\CardType;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Support\Carbon;

class CardSyncRepository
{
    private OauthIntegrationService $oauthIntegrationService;

    public function __construct(OauthIntegrationService $oauthIntegrationService)
    {
        $this->oauthIntegrationService = $oauthIntegrationService;
    }

    // If we synced less than these many seconds ago don't sync again
    private const DONT_SYNC_BEFORE_SECONDS = 86400;

    public function create(array $values): CardSync
    {
        return CardSync::create($values);
    }

    public function getLastAttempt(int $cardId): ?CardSync
    {
        return CardSync::where('card_id', $cardId)->orderBy('created_at', 'desc')->first();
    }

    public function secondsSinceLastAttempt(int $cardId): ?int
    {
        $lastSyncAttempt = $this->getLastAttempt($cardId);
        if ($lastSyncAttempt) {
            return $lastSyncAttempt->created_at->diffInSeconds(Carbon::now());
        }

        return null;
    }

    public function shouldSync(Card $card): bool
    {
        $cardType = CardType::find($card->card_type_id)->name;
        $secondsSinceLastAttempt = $this->secondsSinceLastAttempt($card->id);
        $successfullySyncedBefore = CardSync::where('card_id', $card->id)->where('status', 1)->exists();

        return ! $successfullySyncedBefore ||
            (
                $this->oauthIntegrationService->isIntegrationValid($cardType) &&
                (null === $secondsSinceLastAttempt || $secondsSinceLastAttempt >= self::DONT_SYNC_BEFORE_SECONDS)
            );
    }

    public function shouldGetTags(Card $card, string $newContent): bool
    {
        return false;
    }
}
