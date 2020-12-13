<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleDomains;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;

class SetupGoogleIntegration implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private User $user;
    private Organization $organization;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, Organization $organization)
    {
        $this->user = $user;
        $this->organization = $organization;
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     * @throws JsonException
     */
    public function handle(): void
    {
        app(GoogleDomains::class)->syncDomains($this->user);
        SyncCards::dispatch($this->user->id, $this->organization->id, 'google')->onQueue('sync-cards');
    }
}
