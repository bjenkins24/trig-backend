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
use Illuminate\Support\Facades\Log;
use Throwable;

class SaveCardData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $integration;
    public int $cardId;

    public int $timeout = 90;

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

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // Puppeteer can take a lot of memory
        ini_set('memory_limit', '1024M');
        try {
            $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards($this->integration);
            $card = Card::find($this->cardId);
            $syncCardsIntegration->saveCardData($card);
        } catch (Exception $error) {
            Log::error($error->getMessage());
            app(CardSyncRepository::class)->create([
              'card_id' => $this->cardId,
              'status'  => 0,
            ]);
        }
    }
}
