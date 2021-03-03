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

class SaveThumbnail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Card $card;
    public string $image;
    public string $imageType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $image, string $imageType, Card $card)
    {
        $this->image = $image;
        $this->imageType = $imageType;
        $this->card = $card;
    }

    public function handle(): bool
    {
        ini_set('memory_limit', '1024M');
        try {
            app(ThumbnailHelper::class)->saveThumbnail($this->image, $this->imageType, $this->card);

            return true;
        } catch (Exception $error) {
            Log::error('Saving the thumbnail failed '.$error);

            return false;
        }
    }
}
