<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SaveCardDataInitial implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $integration;
    public Card $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Card $card, string $integration)
    {
        $this->card = $card;
        $this->integration = $integration;
    }

    public function handle(): bool
    {
        try {
            $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards($this->integration);
            $syncCardsIntegration->saveInitialCardData($this->card);

            return true;
        } catch (Exception $error) {
            Log::error('Initial card data fetch failed: '.$error->getMessage());

            return false;
        }
    }
}
