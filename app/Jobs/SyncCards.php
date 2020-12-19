<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
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
    public int $organizationId;
    public string $integration;
    public ?int $since;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        int $userId,
        int $organizationId,
        string $integration,
        ?int $since = null
    ) {
        $this->userId = $userId;
        $this->organizationId = $organizationId;
        $this->integration = $integration;
        $this->since = $since;
    }

    /**
     * @throws CardIntegrationCreationValidate
     * @throws OauthIntegrationNotFound
     */
    public function handle(): void
    {
        $syncCardsIntegration = app(OauthIntegrationService::class)->makeSyncCards($this->integration);

        $syncCardsIntegration->syncCards(User::find($this->userId), Organization::find($this->organizationId), $this->since);
    }
}
