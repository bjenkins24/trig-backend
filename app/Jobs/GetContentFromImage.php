<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Utils\ExtractDataHelper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetContentFromImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Card $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Card $card)
    {
        $this->card = $card;
    }

    public function handle(): bool
    {
        // Tika can take a lot of memory
        ini_set('memory_limit', '1024M');
        try {
            $screenshot = $this->card->properties;
            if (! $screenshot) {
                return false;
            }
            $data = app(ExtractDataHelper::class)->getData(env('CDN_URL').$screenshot);
            $fields = [
                'content' => $data['content'],
            ];
            if (! $this->card->description) {
                $fields['description'] = $data['excerpt'];
            }

            app(CardRepository::class)->updateOrInsert($fields, $this->card);

            return true;
        } catch (Exception $error) {
            Log::error('Getting content from the image failed: '.$error->getMessage());

            return false;
        }
    }
}
