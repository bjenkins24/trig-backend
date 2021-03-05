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
        $extension = substr($card->properties->get('image_thumbnail'), strpos($card->properties->get('image_thumbnail'), $card->token) + strlen($card->token));
        $extensionScreenshot = substr($card->properties->get('screenshot_thumbnail'), strpos($card->properties->get('screenshot_thumbnail'), $card->token) + strlen($card->token));
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/images/{$card->token}{$extension}");
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/image-thumbnails/{$card->token}{$extension}");
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/screenshots/{$card->token}{$extensionScreenshot}");
        Storage::delete('public/'.ThumbnailHelper::IMAGE_FOLDER."/screenshot-thumbnails/{$card->token}{$extensionScreenshot}");
    }
}
