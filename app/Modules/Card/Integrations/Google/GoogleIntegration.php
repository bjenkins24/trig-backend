<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\User;
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
    private OauthConnectionRepository $oauthConnectionRepository;
    private OauthConnectionService $oauthConnectionService;
    private UserRepository $userRepository;

    public function __construct(
        GoogleConnection $googleConnection,
        OauthConnectionRepository $oauthConnectionRepository,
        OauthConnectionService $oauthConnectionService,
        UserRepository $userRepository
    ) {
        $this->googleConnection = $googleConnection;
        $this->oauthConnectionRepository = $oauthConnectionRepository;
        $this->oauthConnectionService = $oauthConnectionService;
        $this->userRepository = $userRepository;
    }

    public static function getIntegrationKey(): string
    {
        return 'google';
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
            $nextPageToken = $service->files->listFiles($params)->getNextPageToken();
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
    private function getPermissions(User $user, $file): array
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
                $carry['link_share'][] = ['type' => 'anyone_organization', 'capability' => $capability];

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
}
