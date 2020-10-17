<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCards implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;
    public int $userId;
    public string $integration;
    public ?int $since;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $userId,
        string $integration,
        ?int $since = null
    ) {
        $this->userId = $userId;
        $this->integration = $integration;
        $this->since = $since;
    }

    /**
     * Execute the job.
     *
     * @throws OauthIntegrationNotFound
     */
    public function handle(): void
    {
        app()->makeWith(SyncCardsIntegration::class, [
            'integration' => app(OauthIntegrationService::class)->makeCardIntegration($this->integration),
        ])->syncCards(User::find($this->userId), $this->since);
    }
}
