<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Modules\CardSync\CardSyncRepository;
use App\Utils\ExtractDataHelper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GetContentFromScreenshot implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Card $card;
    public int $timeout = 300;

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
        ini_set('max_execution_time', 500);
        ini_set('memory_limit', '2048M');
        try {
            $screenshot = $this->card->properties->get('screenshot');
            if (! $screenshot || ! Card::where(['id' => $this->card->id])->exists()) {
                return false;
            }
            $data = app(ExtractDataHelper::class)->getData(Config::get('app.cdn_url').$screenshot);

            // This has to run BEFORE the card is saved with the new content so we can tell if the content has changed
            // enough to get new tags
            $shouldGetTags = app(CardSyncRepository::class)->shouldGetTags($this->card, $data['content']);

            $fields = [
                'content' => $data['content'],
            ];
            if (! $this->card->description) {
                $fields['description'] = $data['excerpt'];
            }

            app(CardRepository::class)->upsert($fields, $this->card);

            if ($shouldGetTags) {
                GetTags::dispatch($this->card);
            }

            return true;
        } catch (Exception $error) {
            Log::error('Getting content from the image failed: '.$error);

            return false;
        }
    }
}
