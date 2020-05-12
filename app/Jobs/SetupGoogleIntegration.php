<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Card\CardService;
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
    private CardService $cardService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, CardService $cardService)
    {
        $this->user = $user;
        $this->cardService = $cardService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->cardService->makeIntegration('google')->syncDomains($this->user);
        SyncCards::dispatch($this->user, 'google', $this->cardService);
    }
}
