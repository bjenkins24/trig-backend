<?php

namespace App\Modules\Card\Integrations;

use App\Models\Card;
use App\Models\CardType;
use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\Permission\PermissionRepository;
use App\Modules\User\UserRepository;
use App\Utils\FileHelper;
use Exception;
use Google_Service_Directory as GoogleServiceDirectory;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Collection;

class GoogleIntegration implements IntegrationInterface
{
    const IMAGE_PATH = 'public/card-thumbnails';
    const PAGE_SIZE = 50;
    const NEXT_PAGE_TOKEN_KEY = 'google_drive_next_page_token';

    /**
     * The keys in this array are google roles and the values are what they map
     * to in Trig.
     */
    const CAPABILITY_MAP = [
        'organizer'     => 'writer',
        'owner'         => 'writer',
        'fileOrganizer' => 'writer',
        'writer'        => 'writer',
        'commenter'     => 'reader',
        'reader'        => 'reader',
    ];

    private $client;

    public function setClient(User $user)
    {
        $this->client = app(OauthConnectionService::class)->getClient($user, GoogleConnection::getKey());
    }

    public function listFilesFromService(GoogleServiceDrive $service, array $params)
    {
        return collect($service->files->listFiles($params));
    }

    /**
     * Get the next page token from google if it exists - this will return null
     * if there's no next page.
     *
     * @return void
     */
    public function getNewNextPageToken(GoogleServiceDrive $service, array $params): ?string
    {
        return $service->files->listFiles($params)->getNextPageToken();
    }

    /**
     * Get the next page token in the database if it exists.
     *
     * @param OauthConnection $oauthConenection
     */
    public function getCurrentNextPageToken(OauthConnection $oauthConnection): ?string
    {
        $pageToken = null;
        if ($oauthConnection->properties) {
            $pageToken = $oauthConnection->properties->get(self::NEXT_PAGE_TOKEN_KEY);
        }

        return $pageToken;
    }

    public function getFiles(User $user)
    {
        if (! $this->client) {
            $this->setClient($user);
        }
        $oauthConnectionRepo = app(OauthConnectionService::class)->repo;
        $oauthConnection = $oauthConnectionRepo->findUserConnection($user, 'google');
        $pageToken = $this->getCurrentNextPageToken($oauthConnection);

        $service = new GoogleServiceDrive($this->client);
        $params = [
            'pageSize'  => self::PAGE_SIZE,
            'fields'    => 'nextPageToken, files',
            'pageToken' => $pageToken,
        ];

        $nextPageToken = $this->getNewNextPageToken($service, $params);
        $oauthConnectionRepo->saveGoogleNextPageToken($oauthConnection, $nextPageToken);

        return $this->listFilesFromService($service, $params);
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
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, GoogleConnection::getKey());
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
    public function saveThumbnail(User $user, Card $card, $file): bool
    {
        if (! $file || ! $file->thumbnailLink) {
            return false;
        }
        $imagePath = self::IMAGE_PATH.'/'.$card->id;
        $thumbnail = $this->getThumbnail($user, $file);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $result = \Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail'));
        if ($result) {
            $card->image = \Config::get('app.url').\Storage::url($imagePathWithExtension);
            $card = $card->save();
        }

        return true;
    }

    public function savePermissions(User $user, Card $card, $file): void
    {
        $linkShareRepo = app(LinkShareSettingRepository::class);
        $permissions = collect($file->permissions);
        $permissionRepo = app(PermissionRepository::class);
        $permissions->each(function ($permission) use ($card, $linkShareRepo, $permissionRepo, $user) {
            $capability = self::CAPABILITY_MAP[$permission->role];
            // Public on the internet - we can make this discoverable in Trig
            if ('anyone' === $permission->type) {
                $linkShareRepo->createPublicIfNew($card, $capability);
            }

            if ('user' === $permission->type) {
                $permissionRepo->createEmail($card, $capability, $permission->emailAddress);
            }

            if ('domain' === $permission->type) {
                // This is public in company - if the domain on the file doesn't exist within
                // Trig then we shouldn't do anything with this permission type - it's giving
                // permission to a domain that Trig isn't aware of
                if (! app(UserRepository::class)->isGoogleDomainActive($user, $permission->domain)) {
                    return false;
                }
                $linkShareRepo->createAnyoneOrganizationIfNew($card, $capability);
            }
        });
    }

    private function createCard(User $user, $file): void
    {
        if ($file->trashed) {
            return;
        }
        $cardType = CardType::firstOrCreate(['name' => $file->mimeType]);

        $card = app(UserRepository::class)->createCard($user, [
            'card_type_id'              => $cardType->id,
            'title'                     => $file->name,
            'actual_created_at'         => $file->createdTime,
            'actual_modified_at'        => $file->modifiedTime,
            'description'               => $file->description,
            'url'                       => $file->webViewLink,
        ]);
        if (! $card) {
            return;
        }
        $this->saveThumbnail($user, $card, $file);
        $this->savePermissions($user, $card, $file);

        app(CardRepository::class)->createIntegration($card, $file->id, GoogleConnection::getKey());
    }

    /**
     * If a user belongs to G Suite, then they will belong to one or more domains.
     * The domain the user belongs to will be used to decide permissions for which cards
     * a user can view.
     *
     * For example, if a user shares a card in G Drive and makes it discoverable to all users
     * on the domain yourmusiclessons.com, we should allow users in Trig to all discover that as well
     *
     * One G Suite account CAN have multiple domains: https://support.google.com/a/answer/7502379
     * Each time a connection is made we will also check their accessible domains. If there is a domain
     * that we don't recognize, we'll add it to the organizations google domains. By default _all_
     * domains will be accessible from within Trig.
     *
     * A Trig admin will be able to select or deselect which domains their Trig account should be
     * accessible for, in the settings for Google from within Trig.
     *
     * @return void
     */
    public function getDomains(User $user): array
    {
        if (! $this->client) {
            $this->setClient($user);
        }
        $service = new GoogleServiceDirectory($this->client);

        try {
            // my_customer get's the domains for the current customer which is what we want
            // weird API, but that's 100% Google
            return $service->domains->listDomains('my_customer')->domains;
        } catch (\Exception $e) {
            $error = json_decode($e->getMessage());
            if (! $error || 404 !== $error->error->code) {
                \Log::notice('Unable to retrieve domains for user. Error: '.json_encode($error));

                return [];
            }

            if (404 === $error) {
                return [];
            }
        }
    }

    public function syncDomains($user): bool
    {
        $domains = $this->getDomains($user);
        if (! $domains) {
            return false;
        }
        $properties = ['google_domains' => []];
        foreach ($domains as $domain) {
            $properties['google_domains'][] = [$domain->domainName => true];
        }
        $user->properties = $properties;
        $user->save();

        return true;
    }

    /**
     * Sync cards from google.
     *
     * @return void
     */
    public function syncCards(User $user): bool
    {
        $files = $this->getFiles($user);

        if (0 === $files->count()) {
            return false;
        }

        $files->each(function ($file) use ($user) {
            $this->createCard($user, $file);
        });

        return true;
    }
}
