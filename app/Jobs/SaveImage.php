<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SaveImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?Card $card;
    public ?string $image;
    public ?string $screenshot;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(?string $image, ?string $screenshot, ?Card $card = null)
    {
        $this->image = $image;
        $this->screenshot = $screenshot;
        $this->card = $card;
    }

    public function handle(): bool
    {
        // Tika can take a lot of memory
        ini_set('memory_limit', '1024M');
        try {
            app(ThumbnailHelper::class)->saveThumbnail($this->image, $this->screenshot, $this->card);

            return true;
        } catch (Exception $error) {
            Log::error('Getting content from the image failed: '.$error->getMessage());

            return false;
        }
    }
}
