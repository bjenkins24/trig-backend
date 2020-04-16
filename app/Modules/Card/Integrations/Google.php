<?php

namespace App\Modules\Card\Integrations;

use App\Models\Card;
use App\Models\CardType;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Utils\FileHelper;
use Exception;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class Google implements IntegrationInterface
{
    const IMAGE_PATH = 'public/card-thumbnails';

    public static function getKey(): string
    {
        return 'google';
    }

    public function getFiles(User $user): Collection
    {
        $client = app(OauthConnectionService::class)->getClient($user, $this->getKey());
        $service = new GoogleServiceDrive($client);
        $optParams = [
            'pageSize' => 100,
            'fields'   => 'nextPageToken, files(id, name, createdTime, modifiedTime, webViewLink, thumbnailLink, starred, iconLink, viewedByMeTime, mimeType)',
        ];

        return collect($service->files->listFiles($optParams)->getFiles());
    }

    /**
     * Get the thumbnail from google.
     *
     * @param $file
     *
     * @return void
     */
    public function getThumbnail(User $user, $file): Collection
    {
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, $this->getKey());
        try {
            $thumbnail = file_get_contents($file->thumbnailLink.'&access_token='.$accessToken);
        } catch (Exception $e) {
            // TODO: Observability?
            // If we couldn't get the thumbnail it's not necessary
            return collect([]);
        }

        $fileInfo = collect(getimagesizefromstring($thumbnail));
        if (! $fileInfo->has('mime')) {
            return collect([]);
        }

        return collect([
            'thumbnail' => $thumbnail,
            'extension' => FileHelper::mimeToExtension($fileInfo->get('mime')),
        ]);
    }

    /**
     * Save the thumbnail from google drive.
     *
     * @param object $file
     */
    public function saveThumbnail(User $user, Card $card, $file): void
    {
        if (! $file || ! $file->thumbnailLink || ! $card) {
            return;
        }
        $imagePath = self::IMAGE_PATH.'/'.$card->id;
        $thumbnail = $this->getThumbnail($user, $file);
        if ($thumbnail->isEmpty()) {
            return;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $result = Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail'));
        if ($result) {
            $card->image = Config::get('app.url').Storage::url($imagePathWithExtension);
            $card = $card->save();
        }
    }

    private function createCard(User $user, $file, CardType $cardType): void
    {
        $card = $user->cards()->create([
            'card_type_id'              => $cardType->id,
            'title'                     => $file->name,
            'actual_created_at'         => $file->createdTime,
            'actual_modified_at'        => $file->modifiedTime,
            'description'               => $file->description,
        ]);
        if (! $card) {
            return;
        }
        $this->saveThumbnail($user, $card, $file);

        $card->cardLink()->create([
            'link' => $file->webViewLink,
        ]);
        $oauthIntegration = OauthIntegration::where(['name' => $this->getKey()])->first();
        $card->cardIntegration()->create([
            'foreign_id'           => $file->id,
            'oauth_integration_id' => $oauthIntegration->id,
        ]);
    }

    /**
     * Sync cards from google.
     *
     * @return void
     */
    public function syncCards($user)
    {
        $files = $this->getFiles($user);

        if (0 === $files->count()) {
            return;
        }

        $cardType = CardType::firstOrCreate(['name' => 'document']);

        $files->each(function ($file) use ($user, $cardType) {
            $this->createCard($user, $file, $cardType);
        });
    }
}
