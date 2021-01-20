<?php

namespace App\Modules\Card\Helpers;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ThumbnailHelper
{
    public const IMAGE_FOLDER = 'card-images';

    private FileHelper $fileHelper;

    public function __construct(FileHelper $fileHelper)
    {
        $this->fileHelper = $fileHelper;
    }

    private function getImage(string $imageUri): Collection
    {
        try {
            $image = $this->fileHelper->makeImage($imageUri);
        } catch (Exception $e) {
            Log::notice("Couldn't get a thumbnail: $imageUri - {$e->getMessage()}");

            return collect([]);
        }

        if (! $image->mime()) {
            Log::notice("Couldn't get a thumbnail. It had no mime type: $imageUri");

            return collect([]);
        }

        return collect([
            'image'     => $image,
            'extension' => $this->fileHelper->mimeToExtension($image->mime()),
        ]);
    }

    private function saveScreenshot(?string $screenshotUri, Card $card): Card
    {
        if (! $screenshotUri) {
            return $card;
        }
        $cardRepository = app(CardRepository::class);
        $screenshotPath = '/'.self::IMAGE_FOLDER.'/full-screenshot/'.$card->token;
        $screenshot = $this->getImage($screenshotUri);
        $screenshotPathWithExtension = $screenshotPath.'.'.$screenshot->get('extension');
        $screenshotResult = Storage::put('public'.$screenshotPathWithExtension, $screenshot->get('image')->encode($screenshot->get('extension'))->__toString());
        if ($screenshotResult) {
            return $cardRepository->setProperties($card, ['full_screenshot' => $screenshotPathWithExtension]);
        }

        return $card;
    }

    public function saveThumbnail(?string $thumbnailUri, ?string $screenshotUri, Card $card): bool
    {
        $this->saveScreenshot($screenshotUri, $card);

        if (! $thumbnailUri) {
            return false;
        }

        $imagePath = '/'.self::IMAGE_FOLDER.'/full/'.$card->token;
        $thumbnailPath = '/'.self::IMAGE_FOLDER.'/thumbnail/'.$card->token;
        $thumbnail = $this->getImage($thumbnailUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $fullResult = Storage::put('public'.$imagePathWithExtension, $thumbnail->get('image')->encode($thumbnail->get('extension'))->__toString());
        $cardRepository = app(CardRepository::class);

        if ($fullResult) {
            $card = $cardRepository->setProperties($card, ['full_image' => $imagePathWithExtension]);
        }

        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->fileHelper->makeImage($thumbnail->get('image'))->resize(251, null, static function ($constraint) {
            $constraint->aspectRatio();
        });
        $result = Storage::put('public'.$thumbnailPathWithExtension, $resizedImage->encode($thumbnail->get('extension'))->__toString());

        $thumbnailUri = str_replace('storage/', '', $thumbnailUri);
        // If we have it locally it should get deleted because we moved it to the thumbnail and image path folders
        Storage::delete($thumbnailUri);
        if ($screenshotUri) {
            unlink($screenshotUri);
        }

        if ($result) {
            $card = $cardRepository->setProperties($card, [
                'thumbnail'        => $thumbnailPathWithExtension,
                'thumbnail_width'  => $resizedImage->width(),
                'thumbnail_height' => $resizedImage->height(),
            ]);
        }

        if ($result || $fullResult) {
            $card->save();
        }

        return true;
    }
}
