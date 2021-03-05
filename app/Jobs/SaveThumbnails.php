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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SaveThumbnails implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Collection $fields;
    public Card $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Collection $fields, Card $card)
    {
        $this->fields = $fields;
        $this->card = $card;
    }

    public function handle(): bool
    {
        ini_set('memory_limit', '1024M');
        $thumbnailHelper = app(ThumbnailHelper::class);
        try {
            if ($this->fields->get('image')) {
                $thumbnailHelper->saveThumbnail($this->fields->get('image'), 'image', $this->card);
            }
            if ($this->fields->get('screenshot')) {
                $thumbnailHelper->saveThumbnail($this->fields->get('screenshot'), 'screenshot', $this->card);
            }

            return true;
        } catch (Exception $error) {
            Log::error('Saving the thumbnail failed '.$error);

            return false;
        }
    }
}
