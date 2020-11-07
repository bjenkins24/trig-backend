<?php

namespace App\Modules\Card\Helpers;

use App\Models\Card;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ThumbnailHelper
{
    public const IMAGE_FOLDER = 'card-thumbnails';

    private FileHelper $fileHelper;

    public function __construct(FileHelper $fileHelper)
    {
        $this->fileHelper = $fileHelper;
    }

    private function getThumbnail(string $thumbnailUri): Collection
    {
        try {
            $thumbnail = $this->fileHelper->fileGetContents($thumbnailUri);
        } catch (Exception $e) {
            Log::notice("Couldn't get a thumbnail: $thumbnailUri - {$e->getMessage()}");

            return collect([]);
        }

        $fileInfo = collect($this->fileHelper->getImageSizeFromString($thumbnail));

        if (! $fileInfo->has('mime')) {
            Log::notice("Couldn't get a thumbnail. It had no mime type: $thumbnailUri");

            return collect([]);
        }

        return collect([
            'thumbnail' => $thumbnail,
            'extension' => $this->fileHelper->mimeToExtension($fileInfo->get('mime')),
            'width'     => $fileInfo->get(0),
            'height'    => $fileInfo->get(1),
        ]);
    }

    public function saveThumbnail(string $thumbnailUri, Card $card): bool
    {
        $imagePath = 'public/'.self::IMAGE_FOLDER.'/'.$card->token;
        $thumbnail = $this->getThumbnail($thumbnailUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $result = Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail'));
        if ($result) {
            $card->image = Config::get('app.url').Storage::url($imagePathWithExtension);
            $card->image_width = $thumbnail->get('width');
            $card->image_height = $thumbnail->get('height');
            $card->save();
        }

        return true;
    }
}
