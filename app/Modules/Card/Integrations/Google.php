<?php

namespace App\Modules\Card\Integrations;

use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\OauthConnection\OauthConnectionService;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Collection;

class Google implements IntegrationInterface
{
    public function getFiles(User $user): Collection
    {
        $client = app(OauthConnectionService::class)->getClient($user, 'google');
        $service = new GoogleServiceDrive($client);
        $optParams = [
            'pageSize' => 100,
            'fields'   => 'nextPageToken, files(id, name, createdTime, modifiedTime, webViewLink, thumbnailLink, starred, iconLink, viewedByMeTime, mimeType)',
        ];

        return collect($service->files->listFiles($optParams)->getFiles());
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
            $user->cards()->create([
                'card_type_id'              => $cardType->id,
                'title'                     => $file->name,
                'actual_created_at'         => $file->createdTime,
                'actual_modified_at'        => $file->modifiedTime,
                'image'                     => $file->thumbnailLink,
                'description'               => $file->description,
            ]);
        });
    }
}
