<?php

namespace App\Modules\Card\Helpers;

use App\Jobs\GetContentFromScreenshot;
use App\Models\Card;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;

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
            $image = $this->fileHelper->makeImage(file_get_contents($imageUri));
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

    /**
     * This will save the image with compression - we are preferring quality over smaller size
     * please only compress images in a lossless format.
     *
     * @throws Exception
     */
    public function saveImage(string $finalPath, Collection $image): Collection
    {
        $tmpName = bin2hex(random_bytes(16));
        $path = 'public/tmp/'.$tmpName.'.'.$image->get('extension');
        $fullTmpPath = 'storage/app/public/tmp/'.$tmpName.'.'.$image->get('extension');
        // Optimization should happen in a queue but I have to save it first because SQS can't handle a big
        // upload. And it's too much trouble, so I'm just not going to optimize the images coming in for now.
//        try {
//            Storage::disk('local')->put($path, $image->get('image')->encode($image->get('extension'))->__toString());
//            $optimizerChain = (new OptimizerChain())
//                ->setTimeout(5)
//                ->addOptimizer(new Jpegoptim([
//                    '--strip-all',
//                    '--all-progressive',
//                ]))
//                ->addOptimizer(new Pngquant())
//                ->addOptimizer(new Svgo())
//                ->addOptimizer(new Gifsicle())
//                ->addOptimizer(new Cwebp());
//
//            $optimizerChain->optimize($fullTmpPath);
//            $image = $this->getImage($fullTmpPath);
//        } catch (Exception $exception) {
//            Log::error('Optimizing the image '.$uri.' failed: '.$exception);
//        }
        $result = Storage::put('public'.$finalPath.'.'.$image->get('extension'), file_get_contents($fullTmpPath));
        Storage::disk('local')->delete($path);

        return collect([
            'successful' => $result,
            'extension'  => $image->get('extension'),
        ]);
    }

    private function adjustThumbnail($thumbnail, int $finalWidth, int $finalHeight): Image
    {
        $image = $this->fileHelper->makeImage($thumbnail->get('image'));
        $cropHeight = $image->width() * ($finalHeight / $finalWidth);

        $height = $image->height();
        $thumbnailHeight = $height;
        if ($height > $cropHeight) {
            $thumbnailHeight = $cropHeight;
        }
        $image = $image->crop($image->width(), (int) $thumbnailHeight, 0, 0);

        return $image->resize($finalWidth, null, static function ($constraint) {
            $constraint->aspectRatio();
        });
    }

    /**
     * @throws Exception
     */
    public function saveThumbnail(string $imageUri, string $type, Card $card): bool
    {
        $imagePath = '/'.self::IMAGE_FOLDER.'/'.$type.'s/'.$card->token;
        $thumbnailPath = '/'.self::IMAGE_FOLDER.'/'.$type.'-thumbnails/'.$card->token;
        $largeThumbnailPath = '/'.self::IMAGE_FOLDER.'/'.$type.'-large-thumbnails/'.$card->token;
        $thumbnail = $this->getImage($imageUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $fullResult = $this->saveImage($imagePath, $thumbnail);

        if ($fullResult->get('successful')) {
            $card->setProperties([$type => $imagePath.'.'.$fullResult->get('extension')]);
        }

        // Small Thumbnail
        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->adjustThumbnail($thumbnail, 251, 175);
        $resultSmall = $this->saveImage($thumbnailPath, collect(['image' => $resizedImage, 'extension' => $thumbnail->get('extension')]));

        if ('screenshot' === $type) {
            // Large thumbnail
            $largeThumbnailPathWithExtension = $largeThumbnailPath.'.'.$thumbnail->get('extension');
            $largeResizedImage = $this->adjustThumbnail($thumbnail, 800, 800);
            $resultLarge = $this->saveImage($largeThumbnailPath, collect(['image' => $largeResizedImage, 'extension' => $thumbnail->get('extension')]));
        }

        if (file_exists($imageUri)) {
            unlink($imageUri);
        }

        if ($resultSmall->get('successful')) {
            $card->setProperties([
                $type.'_thumbnail'        => $thumbnailPathWithExtension,
                $type.'_thumbnail_width'  => $resizedImage->width(),
                $type.'_thumbnail_height' => $resizedImage->height(),
            ]);
        }

        if ('screenshot' === $type && $resultLarge->get('successful')) {
            $card->setProperties([
                $type.'_thumbnail_large'        => $largeThumbnailPathWithExtension,
                $type.'_thumbnail_large_width'  => $largeResizedImage->width(),
                $type.'_thumbnail_large_height' => $largeResizedImage->height(),
            ]);
        }

        if ($resultSmall || (isset($resultLarge) && $resultLarge) || $fullResult) {
            $card->save();
        }

        return true;
    }

    public function saveThumbnails(Collection $fields, Card $card, ?bool $getContentFromScreenshot = false): ?bool
    {
        ini_set('memory_limit', '1024M');
        try {
            if ($this->fields->get('image')) {
                $this->saveThumbnail($fields->get('image'), 'image', $card);
            }
            if ($fields->get('screenshot')) {
                $this->saveThumbnail($fields->get('screenshot'), 'screenshot', $card);
            }

            if ($getContentFromScreenshot) {
                GetContentFromScreenshot::dispatch($card);
            }

            return true;
        } catch (Exception $error) {
            Log::error('Saving the thumbnail failed '.$error);

            return false;
        }
    }
}
