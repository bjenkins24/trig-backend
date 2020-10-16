<?php

namespace App\Modules\Card\Integrations;

use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\Card\Interfaces\IntegrationInterface2;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\User\UserRepository;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GoogleIntegration2 implements IntegrationInterface2
{
    public const IMAGE_PATH = 'public/card-thumbnails';
    public const PAGE_SIZE = 30;
    public const NEXT_PAGE_TOKEN_KEY = 'google_drive_next_page_token';

    public const WEBHOOK_URL = '/webhooks/google-drive';

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

    private $client;

    /**
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function setClient(User $user): void
    {
        $this->client = app(OauthConnectionService::class)->getClient($user, GoogleConnection::getKey());
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function getDriveService(User $user): GoogleServiceDrive
    {
        if (! $this->client) {
            $this->setClient($user);
        }

        return new GoogleServiceDrive($this->client);
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
     * Get the next page token in the database if it exists.
     */
    public function getCurrentNextPageToken(OauthConnection $oauthConnection): ?string
    {
        $pageToken = null;
        if ($oauthConnection->properties) {
            $pageToken = $oauthConnection->properties->get(self::NEXT_PAGE_TOKEN_KEY);
        }

        return $pageToken;
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function getFiles(User $user, ?int $since = null)
    {
        $oauthConnectionRepo = app(OauthConnectionRepository::class);
        $oauthConnection = $oauthConnectionRepo->findUserConnection($user, 'google');
        if (null === $oauthConnection) {
            throw new \RuntimeException('The oauth connection was not found');
        }
        $pageToken = $this->getCurrentNextPageToken($oauthConnection);

        $service = $this->getDriveService($user);

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
            $oauthConnectionRepo->saveGoogleNextPageToken($oauthConnection, $nextPageToken);
        }

        return collect($service->files->listFiles($params));
    }

    /**
     * @param $file
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function getThumbnailLink(User $user, $file): string
    {
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, GoogleConnection::getKey());
        $delimiter = '?';
        if (Str::contains($file->thumbnailLink, $delimiter)) {
            $delimiter = '&';
        }

        return $file->thumbnailLink.$delimiter.'access_token='.$accessToken;
    }

    /**
     * @param $file
     */
    public function getPermissions($file): array
    {
        $googlePermissions = collect($file->permissions);

        return $googlePermissions->reduce(static function ($carry, $permission) {
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
                if (! app(UserRepository::class)->isGoogleDomainActive($user, $permission->domain)) {
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
     * @throws OauthMissingTokens
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

        $permissions = $this->getPermissions($file);

        return [
            'data'        => $cardData,
            'permissions' => $permissions,
        ];
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function getAllCardData(User $user, ?int $since): Collection
    {
        $files = $this->getFiles($user, $since);

        return collect($files->reduce(function ($carry, $file) use ($user) {
            $carry[] = $this->getCardData($user, $file);

            return $carry;
        }, []));
    }
}
