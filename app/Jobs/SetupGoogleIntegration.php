<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SetupGoogleIntegration implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private User $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app(OauthIntegrationService::class)->makeCardIntegration('google')->syncDomains($this->user);
        SyncCards::dispatch($this->user, 'google')->onQueue('sync-cards');
    }
}
