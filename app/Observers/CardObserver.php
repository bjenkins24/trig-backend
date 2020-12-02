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
        $extension = substr($card->image, strpos($card->image, $card->token) + strlen($card->token));
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/thumbnail/{$card->token}{$extension}");
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/full/{$card->token}{$extension}");
    }
}
