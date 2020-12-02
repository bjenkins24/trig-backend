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
        Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail')->encode($thumbnail->get('extension'))->__toString());

        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->fileHelper->makeImage($thumbnail->get('thumbnail'))->resize(251, null, static function ($constraint) {
            $constraint->aspectRatio();
        });
        $result = Storage::put($thumbnailPathWithExtension, $resizedImage->encode($thumbnail->get('extension'))->__toString());
        if ($result) {
            $card->image = Config::get('app.url').Storage::url($thumbnailPathWithExtension);
            $card->image_width = $resizedImage->width();
            $card->image_height = $resizedImage->height();
            $card->save();
        }

        return true;
    }
}
