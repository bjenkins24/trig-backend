<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\User\UserRepository;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public User $user;

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
     * @throws Throwable
     */
    public function handle(): bool
    {
        ini_set('max_execution_time', 120);

        if (empty($this->user->properties) && empty($this->user->properties->tagged_for_deletion)) {
            Log::error("The delete job ran for a user that wasn't marked for deletion: {$this->user->id}");

            return false;
        }

        try {
            app(UserRepository::class)->delete($this->user);

            return true;
        } catch (Exception $e) {
            Log::error('The delete user job failed: '.$e->getMessage());

            return false;
        }
    }
}
