<?php

namespace Tests\Observers;

use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Observers\CardObserver;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardObserverTest extends TestCase
{
    public function testDeleted(): void
    {
        Storage::fake();
        $card = Card::find(1);
        $imagePath = 'public/'.ThumbnailHelper::IMAGE_FOLDER.'/image-thumbnails/'.$card->token.'.jpg';
        Storage::put($imagePath, 'coolthing.jpg');
        Storage::assertExists($imagePath);

        (new CardObserver())->deleted(Card::find(1));

        Storage::assertMissing($imagePath);
    }
}
