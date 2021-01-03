<?php

namespace App\Modules\Card\Helpers;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
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

    private function getThumbnail(string $thumbnailUri): Collection
    {
        try {
            $thumbnail = $this->fileHelper->makeImage($thumbnailUri);
        } catch (Exception $e) {
            Log::notice("Couldn't get a thumbnail: $thumbnailUri - {$e->getMessage()}");

            return collect([]);
        }

        if (! $thumbnail->mime()) {
            Log::notice("Couldn't get a thumbnail. It had no mime type: $thumbnailUri");

            return collect([]);
        }

        return collect([
            'thumbnail' => $thumbnail,
            'extension' => $this->fileHelper->mimeToExtension($thumbnail->mime()),
        ]);
    }

    public function saveThumbnail(string $thumbnailUri, Card $card): bool
    {
        $imagePath = 'public/'.self::IMAGE_FOLDER.'/full/'.$card->token;
        $thumbnailPath = 'public/'.self::IMAGE_FOLDER.'/thumbnail/'.$card->token;
        $thumbnail = $this->getThumbnail($thumbnailUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $fullResult = Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail')->encode($thumbnail->get('extension'))->__toString());
        $cardRepository = app(CardRepository::class);

        if ($fullResult) {
            $card = $cardRepository->setProperties($card, ['full_image' => Config::get('app.url').Storage::url($imagePathWithExtension)]);
        }

        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->fileHelper->makeImage($thumbnail->get('thumbnail'))->resize(251, null, static function ($constraint) {
            $constraint->aspectRatio();
        });
        $result = Storage::put($thumbnailPathWithExtension, $resizedImage->encode($thumbnail->get('extension'))->__toString());

        $thumbnailUri = str_replace('storage/', '', $thumbnailUri);
        // If we have it locally it should get deleted because we moved it to the thumbnail and image path folders
        Storage::delete($thumbnailUri);

        if ($result) {
            $card = $cardRepository->setProperties($card, [
                'thumbnail'        => Config::get('app.url').Storage::url($thumbnailPathWithExtension),
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
