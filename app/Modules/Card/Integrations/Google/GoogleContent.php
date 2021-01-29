<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Utils\ExtractDataHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GoogleContent implements ContentInterface
{
    private GoogleConnection $googleConnection;
    private CardRepository $cardRepository;
    private ExtractDataHelper $extractDataHelper;

    public function __construct(
        GoogleConnection $googleConnection,
        CardRepository $cardRepository,
        ExtractDataHelper $extractDataHelper
    ) {
        $this->googleConnection = $googleConnection;
        $this->cardRepository = $cardRepository;
        $this->extractDataHelper = $extractDataHelper;
    }

    /**
     * Google apps can be exported to normal file mime types. We need to know what to convert
     * which is what this function does.
     */
    public function googleToMime(string $mimeType): string
    {
        $googleTypes = [
            'audio'        => '',
            'document'     => 'text/plain',
            'drawing'      => 'application/pdf',
            'drive-sdk'    => '',
            'file'         => '',
            'folder'       => '',
            'form'         => '',
            'fusiontable'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'map'          => 'application/pdf',
            'photo'        => 'image/jpeg',
            'presentation' => 'text/plain',
            'script'       => 'application/vnd.google-apps.script+json',
            'shortcut'     => '',
            'site'         => 'application/pdf',
            'spreadsheet'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'unknown'      => '',
            'video'        => '',
        ];
        $type = Str::replaceFirst('application/vnd.google-apps.', '', $mimeType);

        if (! isset($googleTypes[$type])) {
            return '';
        }

        return $googleTypes[$type];
    }

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function getCardContent(Card $card, string $id, ?string $mimeType = null): string
    {
        $service = $this->googleConnection->getDriveService($this->cardRepository->getUser($card));

        // G Suite files need to be exported
        if (Str::contains($mimeType, 'application/vnd.google-apps')) {
            $mimeType = $this->googleToMime($mimeType);
            if (! $mimeType) {
                return '';
            }
            $content = $service->files->export($id, $mimeType);
        } else {
            $content = $service->files->get($id, ['alt' => 'media']);
        }

        return $content->getBody();
    }

    public function getCardInitialData(Card $card): Collection
    {
        return collect([]);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getCardContentData(Card $card, ?string $id, ?string $mimeType): Collection
    {
        $content = $this->getCardContent($card, $id, $mimeType);
        $data = $this->extractDataHelper->getFileData($mimeType, $content);

        return collect(array_merge($data->toArray(), ['content' => $content]));
    }
}
