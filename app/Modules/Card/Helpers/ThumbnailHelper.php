<?php

namespace App\Modules\Card\Helpers;

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
        try {
            Storage::disk('local')->put($path, $image->get('image')->encode($image->get('extension'))->__toString());
            $optimizerChain = (new OptimizerChain())
                ->setTimeout(5)
                ->addOptimizer(new Jpegoptim([
                    '--strip-all',
                    '--all-progressive',
                ]))
                ->addOptimizer(new Pngquant())
                ->addOptimizer(new Svgo())
                ->addOptimizer(new Gifsicle())
                ->addOptimizer(new Cwebp());

            $optimizerChain->optimize($fullTmpPath);
            $image = $this->getImage($fullTmpPath);
        } catch (Exception $exception) {
            Log::error('Optimizing the image '.$uri.' failed: '.$exception);
        }
        $result = Storage::put('public'.$finalPath.'.'.$image->get('extension'), file_get_contents($fullTmpPath));
        Storage::disk('local')->delete($path);

        return collect([
            'successful' => $result,
            'extension'  => $image->get('extension'),
        ]);
    }

    private function adjustThumbnail(Card $card, $thumbnail, string $type): Image
    {
        $image = $this->fileHelper->makeImage($thumbnail->get('image'));
        if ('screenshot' === $type) {
            $width = $image->width();
            $height = $image->height();

            // TODO: If we end up doing a lot of these this should be abstracted to a class
            // We need to remove these when I have time to work on it. Ideally we would
            // resize a page with HTML in the extension to make it the size of the screenshot
            // we want to take. Then later (here) we can crop it so the white edges are cut off.
            // That would make a beautiful thumbnail. But it's a lot of work if it's even possible
            // The screenshot thumbnails are going to be worse as screensizes get bigger. I think I'm
            // ok with that tradeoff for now so we can get it out
            if (false !== strpos($card->url, 'docs.google.com')) {
                $thumbnailCropWidth = 800;
                $thumbnailCropHeight = 800;
                $xPosition = 0;
                if ($width >= $thumbnailCropWidth) {
                    $xPosition = (int) (($width - $thumbnailCropWidth) / 2 - $width / 52);
                }
                $yPosition = 0;
                if ($height >= $thumbnailCropHeight) {
                    // Arbitrary to cut off the header that probably exists
                    $yPosition = 150;
                }
                $image = $image->crop($thumbnailCropWidth, $thumbnailCropHeight, $xPosition, $yPosition);
            }
            if (false !== strpos($card->url, 'google.com/search?')) {
                $thumbnailCropWidth = 710;
                $thumbnailCropHeight = 900;
                $xPosition = 150;
                $yPosition = 0;
                $image = $image->crop($thumbnailCropWidth, $thumbnailCropHeight, $xPosition, $yPosition);
            }

            if (! isset($xPosition)) {
                $image = $image->crop($width, $height >= 1200 ? 1200 : $height, 0, 0);
            }
        }

        return $image->resize(251, null, static function ($constraint) {
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
        $thumbnail = $this->getImage($imageUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $fullResult = $this->saveImage($imagePath, $thumbnail);

        if ($fullResult->get('successful')) {
            $card->setProperties([$type => $imagePath.'.'.$fullResult->get('extension')]);
        }

        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->adjustThumbnail($card, $thumbnail, $type);

        $result = $this->saveImage($thumbnailPath, collect(['image' => $resizedImage, 'extension' => $thumbnail->get('extension')]));

        if (file_exists($imageUri)) {
            unlink($imageUri);
        }

        if ($result->get('successful')) {
            $card->setProperties([
                $type.'_thumbnail'        => $thumbnailPathWithExtension,
                $type.'_thumbnail_width'  => $resizedImage->width(),
                $type.'_thumbnail_height' => $resizedImage->height(),
            ]);
        }

        if ($result || $fullResult) {
            $card->save();
        }

        return true;
    }
}
