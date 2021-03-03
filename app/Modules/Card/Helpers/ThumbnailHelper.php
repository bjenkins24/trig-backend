<?php

namespace App\Modules\Card\Helpers;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /**
     * @throws Exception
     */
    public function saveThumbnail(string $imageUri, string $type, Card $card): bool
    {
        $imagePath = '/'.self::IMAGE_FOLDER.'/'.$type.'/'.$card->token;
        $thumbnailPath = '/'.self::IMAGE_FOLDER.'/'.$type.'-thumbnails/'.$card->token;
        $thumbnail = $this->getImage($imageUri);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $fullResult = $this->saveImage($imagePath, $thumbnail);
        $cardRepository = app(CardRepository::class);

        if ($fullResult->get('successful')) {
            $card = $cardRepository->setProperties($card, [$type => $imagePath.'.'.$fullResult->get('extension')]);
        }

        $thumbnailPathWithExtension = $thumbnailPath.'.'.$thumbnail->get('extension');
        $resizedImage = $this->fileHelper->makeImage($thumbnail->get('image'))->resize(251, null, static function ($constraint) {
            $constraint->aspectRatio();
        });
        $result = $this->saveImage($thumbnailPath, collect(['image' => $resizedImage, 'extension' => $thumbnail->get('extension')]));

        if (file_exists($imageUri)) {
            unlink($imageUri);
        }

        if ($result->get('successful')) {
            $card = $cardRepository->setProperties($card, [
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
