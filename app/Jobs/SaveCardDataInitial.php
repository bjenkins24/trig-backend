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
    public int $cardId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $cardId, string $integration)
    {
        $this->cardId = $cardId;
        $this->integration = $integration;
    }

    public function handle(): bool
    {
        ini_set('memory_limit', '1024M');
        try {
            $card = Card::find($this->cardId);
            $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards($this->integration);
            $syncCardsIntegration->saveInitialCardData($card);

            return true;
        } catch (Exception $error) {
            Log::error('Initial card data fetch failed: '.$error->getMessage());

            return false;
        }
    }
}
