<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\Card;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\User\UserRepository;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GoogleIntegration implements IntegrationInterface
{
    public const PAGE_SIZE = 30;
    public const NEXT_PAGE_TOKEN_KEY = 'google_drive_next_page_token';

    /**
     * The keys in this array are google roles and the values are what they map
     * to in Trig.
     */
    public const CAPABILITY_MAP = [
        'commenter'     => 'reader',
        'fileOrganizer' => 'writer',
        'organizer'     => 'writer',
        'owner'         => 'writer',
        'reader'        => 'reader',
        'writer'        => 'writer',
    ];

    private GoogleConnection $googleConnection;
    private CardRepository $cardRepository;
    private OauthConnectionRepository $oauthConnectionRepository;
    private OauthConnectionService $oauthConnectionService;
    private UserRepository $userRepository;

    public function __construct(
        GoogleConnection $googleConnection,
        CardRepository $cardRepository,
        OauthConnectionRepository $oauthConnectionRepository,
        OauthConnectionService $oauthConnectionService,
        UserRepository $userRepository
    ) {
        $this->googleConnection = $googleConnection;
        $this->cardRepository = $cardRepository;
        $this->oauthConnectionRepository = $oauthConnectionRepository;
        $this->oauthConnectionService = $oauthConnectionService;
        $this->userRepository = $userRepository;
    }

    public static function getIntegrationKey(): string
    {
        return 'google';
    }

    /**
     * Get the next page token from google if it exists - this will return null
     * if there's no next page.
     */
    public function getNewNextPageToken(GoogleServiceDrive $service, array $params): ?string
    {
        return $service->files->listFiles($params)->getNextPageToken();
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getFiles(User $user, ?int $since = null)
    {
        $pageToken = $this->oauthConnectionRepository->getNextPageToken($user, self::getIntegrationKey());

        $service = $this->googleConnection->getDriveService($user);

        if ($since) {
            $params = [
                'fields' => 'files',
                'q'      => "modifiedTime > '".Carbon::createFromTimestamp($since)->toDateTimeLocalString()."'",
            ];
        } else {
            $params = ['fields' => 'nextPageToken, files', 'pageToken' => $pageToken];
        }

        $params = array_merge([
            'pageSize'  => self::PAGE_SIZE,
        ], $params);

        if (! $since) {
            $nextPageToken = $this->getNewNextPageToken($service, $params);
            $this->oauthConnectionRepository->saveNextPageToken($user, self::getIntegrationKey(), $nextPageToken);
        }

        return collect($service->files->listFiles($params));
    }

    /**
     * @param $file
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getThumbnailLink(User $user, $file): string
    {
        $accessToken = $this->oauthConnectionService->getAccessToken($user, self::getIntegrationKey());
        $delimiter = '?';
        if (Str::contains($file->thumbnailLink, $delimiter)) {
            $delimiter = '&';
        }

        return $file->thumbnailLink.$delimiter.'access_token='.$accessToken;
    }

    /**
     * @param $file
     */
    public function getPermissions(User $user, $file): array
    {
        $googlePermissions = collect($file->permissions);

        return $googlePermissions->reduce(function ($carry, $permission) use ($user) {
            $capability = self::CAPABILITY_MAP[$permission->role];
            // Public on the internet - we can make this discoverable in Trig
            if ('anyone' === $permission->type) {
                $carry['link_share'][] = ['type' => 'public', 'capability' => $capability];

                return $carry;
            }

            if ('user' === $permission->type) {
                $carry['users'][] = ['email' => $permission->emailAddress, 'capability' => $capability];

                return $carry;
            }

            if ('domain' === $permission->type) {
                // This is public in company - if the domain on the file doesn't exist within
                // Trig then we shouldn't do anything with this permission type - it's giving
                // permission to a domain that Trig isn't aware of
                if (! $this->userRepository->isGoogleDomainActive($user, $permission->domain)) {
                    return $carry;
                }
                $carry['link_share'] = ['type' => 'anyone_organization', 'capability' => $capability];

                return $carry;
            }

            return $carry;
        }, [
            'users'      => [],
            'link_share' => [],
        ]);
    }

    /**
     * @param $file
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getCardData(User $user, $file): array
    {
        if ('application/vnd.google-apps.folder' === $file->mimeType) {
            return [];
        }

        $cardData = [
            'user_id'            => $user->id,
            'delete'             => $file->trashed,
            'card_type'          => $file->mimeType,
            'url'                => $file->webViewLink,
            'foreign_id'         => $file->id,
            'title'              => $file->name,
            'description'        => $file->description,
            'actual_created_at'  => $file->createdTime,
            'actual_modified_at' => $file->modifiedTime,
            'thumbnail_uri'      => $this->getThumbnailLink($user, $file),
        ];

        $permissions = $this->getPermissions($user, $file);

        return [
            'data'        => $cardData,
            'permissions' => $permissions,
        ];
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getAllCardData(User $user, ?int $since): array
    {
        $files = $this->getFiles($user, $since);

        return $files->reduce(function ($carry, $file) use ($user) {
            $carry[] = $this->getCardData($user, $file);

            return $carry;
        }, []);
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

        return $googleTypes[$type];
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getCardContent(Card $card, int $id, string $mimeType)
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
}
