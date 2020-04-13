<?php

namespace App\Modules\Card\Integrations;

use App\Modules\Card\Interfaces\IntegrationInterface;
use Google_Service_Drive as GoogleServiceDrive;

class Google extends BaseIntegration implements IntegrationInterface
{
    /**
     * Sync cards from google.
     *
     * @return void
     */
    public function syncCards()
    {
        $service = app()->makeWith(GoogleServiceDrive::class, ['client' => $this->client]);

        $optParams = [
            'pageSize' => 100,
            'fields'   => 'nextPageToken, files(id, name, createdTime, modifiedTime, webViewLink, thumbnailLink, starred, iconLink, viewedByMeTime, mimeType)',
        ];
        $results = $service->files->listFiles($optParams);

        $result = [];
        if (0 === count($results->getFiles())) {
            return $result;
        }

        foreach ($results->getFiles() as $file) {
            $result[$file->getId()] = [
                'name'           => $file->name,
                'created'        => $file->createdTime,
                'updated'        => $file->modifiedTime,
                'link'           => $file->webViewLink,
                'image'          => $file->thumbnailLink,
                'starred'        => $file->starred,
                'typeIcon'       => $file->iconLink,
                'viewedByMeTime' => $file->viewedByMeTime,
                'mimeType'       => $file->mimeType,
                // 'permissions' => $file->permissions
            ];
        }

        return $result;
    }
}
