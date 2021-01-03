<?php

namespace App\Observers;

use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Illuminate\Support\Facades\Storage;

class CardObserver
{
    /**
     * Handle the card "deleted" event.
     */
    public function deleted(Card $card): void
    {
        if (empty($card->properties)) {
            return;
        }
        $extension = substr($card->properties->get('thumbnail'), strpos($card->properties->get('thumbnail'), $card->token) + strlen($card->token));
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/thumbnail/{$card->token}{$extension}");
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/full/{$card->token}{$extension}");
    }
}
