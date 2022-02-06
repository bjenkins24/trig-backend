<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Card\Integrations\Twitter\Bookmarks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TwitterBookmarks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;
    public User $user;
    public array $arrayOfRawHtml;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        array $arrayOfRawHtml,
        User $user
    ) {
        $this->user = $user;
        $this->arrayOfRawHtml = $arrayOfRawHtml;
    }

    public function handle(): void
    {
        app(Bookmarks::class)->saveTweetsFromArrayOfHtml($this->arrayOfRawHtml, $this->user);
    }
}
