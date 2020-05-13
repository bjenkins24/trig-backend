<?php

namespace App\Jobs;

use App\Models\User;
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

    /**
     * @var User
     */
    public User $user;

    /**
     * @var string
     */
    public string $integration;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        User $user,
        string $integration
    ) {
        $this->user = $user;
        $this->integration = $integration;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app(OauthIntegrationService::class)->makeCardIntegration($this->integration)->syncCards($this->user);
    }
}
