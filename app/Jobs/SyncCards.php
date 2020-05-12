<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Card\CardService;
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
     * @var string
     */
    private CardService $cardService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        User $user, 
        string $integration, 
        CardService $cardService
    ) {
        $this->cardService = $cardService;
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
        $this->cardService->makeIntegration($this->integration)->syncCards($this->user);
    }
}
