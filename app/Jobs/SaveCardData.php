<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
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

    /**
     * Execute the job.
     *
     * @throws OauthIntegrationNotFound
     *
     * @return void
     */
    public function handle()
    {
        app(OauthIntegrationService::class)->makeCardIntegration($this->integration)->saveCardData($this->card);
    }
}
