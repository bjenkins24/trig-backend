<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\CardSync\CardSyncRepository;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveCardData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $integration;
    public bool $forceSync;
    public Card $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Card $card, string $integration, bool $forceSync = false)
    {
        $this->card = $card;
        $this->integration = $integration;
        $this->forceSync = $forceSync;
    }

    public function handle(): void
    {
        try {
            $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards($this->integration);
            $syncCardsIntegration->saveCardData($this->card, $this->forceSync);
        } catch (Exception $error) {
            app(CardSyncRepository::class)->create([
              'card_id' => $this->card->id,
              'status'  => 0,
            ]);
        }
    }
}
