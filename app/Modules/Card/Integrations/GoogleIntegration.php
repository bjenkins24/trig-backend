<?php

namespace App\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Jobs\SaveCardData;
use App\Jobs\SyncCards;
use App\Models\Card;
use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthConnection\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\Permission\PermissionRepository;
use App\Modules\User\UserRepository;
use App\Utils\ExtractDataHelper;
use App\Utils\FileHelper;
use Exception;
use Google_Service_Directory as GoogleServiceDirectory;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleIntegration implements IntegrationInterface
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

    public function listFilesFromService(GoogleServiceDrive $service, array $params)
    {
        return collect($service->files->listFiles($params));
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

        return $this->listFilesFromService($service, $params);
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
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function saveCardData(Card $card): void
    {
        $cardRepo = app(CardRepository::class);
        $cardIntegration = $cardRepo->getCardIntegration($card);
        if (! $cardIntegration) {
            return;
        }
        $id = $cardIntegration->foreign_id;
        $mimeType = $cardRepo->getCardType($card)->name;

        $service = $this->getDriveService($cardRepo->getUser($card));

        // G Suite files need to be exported
        if (\Str::contains($mimeType, 'application/vnd.google-apps')) {
            $mimeType = $this->googleToMime($mimeType);
            if (! $mimeType) {
                return;
            }
            $content = $service->files->export($id, $mimeType);
        } else {
            $content = $service->files->get($id, ['alt' => 'media']);
        }

        $data = app(ExtractDataHelper::class)->getFileData($mimeType, $content->getBody());

        // Save the card data retrieved from the extraction
        $card->content = $data->get('content');
        $data->forget('content');
        $data = $data->reject(function ($value) {
            return ! $value;
        });
        $card->properties = $data->toArray();
        $card->save();

        if ($card->content) {
            CardDedupe::dispatch($card)->onQueue('card-dedupe');
        }
    }

    /**
     * Get the thumbnail from google.
     *
     * @param $file
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function getThumbnail(User $user, $file): Collection
    {
        $accessToken = app(OauthConnectionService::class)->getAccessToken($user, GoogleConnection::getKey());
        try {
            $delimiter = '?';
            if (\Str::contains($file->thumbnailLink, $delimiter)) {
                $delimiter = '&';
            }
            $thumbnail = file_get_contents($file->thumbnailLink.$delimiter.'access_token='.$accessToken);
        } catch (Exception $e) {
            Log::notice('Couldn\'t get a thumbnail: '.$file->thumbnailLink.' - '.$e->getMessage());

            return collect([]);
        }

        $fileInfo = collect(getimagesizefromstring($thumbnail));

        if (! $fileInfo->has('mime')) {
            Log::notice('Couldn\'t get a thumbnail. It had no mime type: '.$file->thumbnailLink);

            return collect([]);
        }

        return collect([
            'thumbnail' => $thumbnail,
            'extension' => FileHelper::mimeToExtension($fileInfo->get('mime')),
            'width'     => $fileInfo->get(0),
            'height'    => $fileInfo->get(1),
        ]);
    }

    /**
     * Save the thumbnail from google drive.
     *
     * @param $file
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
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
            $card->image_width = $thumbnail->get('width');
            $card->image_height = $thumbnail->get('height');
            $card->save();
        }

        return true;
    }

    public function savePermissions(User $user, Card $card, $file): void
    {
        $linkShareRepo = app(LinkShareSettingRepository::class);
        $permissions = collect($file->permissions);
        $permissionRepo = app(PermissionRepository::class);

        // Remove permissions first so the sync isn't creating duplicates if we're just updating
        app(CardRepository::class)->removeAllPermissions($card);

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

    /**
     * @param $file
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     * @throws CardIntegrationCreationValidate
     * @throws Exception
     */
    public function upsertCard(User $user, $file): void
    {
        $cardRepo = app(CardRepository::class);
        $card = $cardRepo->getByForeignId($file->id);
        $isNew = false;
        if (! $card) {
            $isNew = true;
        }

        if ($file->trashed && $card) {
            $card->delete();

            return;
        }

        $needsUpdating = $cardRepo->needsUpdate($card, strtotime($file->modifiedTime));

        // Don't save trashed files or google drive folders
        // TODO: Save google drive folders so files can use the folders as tags
        if (
            $file->trashed ||
            'application/vnd.google-apps.folder' === $file->mimeType ||
            ! $needsUpdating
        ) {
            return;
        }

        $cardType = app(CardTypeRepository::class)->firstOrCreate($file->mimeType);

        $card = $cardRepo->updateOrInsert([
            'actual_created_at'         => $file->createdTime,
            'actual_modified_at'        => $file->modifiedTime,
            'card_type_id'              => $cardType->id,
            'description'               => $file->description,
            'title'                     => $file->name,
            'url'                       => $file->webViewLink,
            'user_id'                   => $user->id,
        ], $card);

        if (! $card) {
            return;
        }
        $this->saveThumbnail($user, $card, $file);
        $this->savePermissions($user, $card, $file);

        if ($isNew) {
            $cardRepo->createIntegration($card, $file->id, GoogleConnection::getKey());
        }

        if (! ExtractDataHelper::isExcluded($file->mimeType)) {
            SaveCardData::dispatch($card, 'google')->onQueue('card-data');
        }
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
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
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
        } catch (Exception $e) {
            $error = json_decode($e->getMessage());
            if (! $error || 404 !== $error->error->code) {
                \Log::notice('Unable to retrieve domains for user. Error: '.json_encode($error));
            }
        }

        return [];
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function syncDomains(User $user): bool
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

    public function watchFiles(User $user): void
    {
        $webhookId = Str::uuid();

        $oauthConnection = app(UserRepository::class)->getOauthConnection($user, 'google');
        // If we are already watching for changes then no need to hit endpoint again
        if ($oauthConnection->properties->webhook_id) {
            return;
        }

        try {
            $expiration = Carbon::now()->addSeconds(604800)->timestamp;
            Http::post('https://www.googleapis.com/drive/v3/changes/watch', [
                'id'              => $webhookId,
                'address'         => Config::get('app.url').self::WEBHOOKS_URL,
                'type'            => 'web_hook',
                'expiration'      => $expiration * 1000,
            ]);
            $oauthConnection->properties['webhook_id'] = $webhookId;
            $oauthConnection->properties['webhook_expiration'] = $expiration;
            $oauthConnection->save();
        } catch (Exception $e) {
            Log::error('Unable to watch google drive for changes for user '.$user->id.': '.$e->getMessage());
        }
    }

    public function getCardData() {

    }

    /**
     * Sync cards from google.
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function syncCards(int $userId, ?int $since = null): bool
    {
        $user = User::find($userId);
        $files = $this->getFiles($user);

        if (0 === $files->count()) {
            return false;
        }

        $files->each(function ($file) use ($user) {
            $this->upsertCard($user, $file);
        });

        // Run the next page of syncing
        $oauthConnection = app(UserRepository::class)->getOauthConnection($user, GoogleConnection::getKey());
        if ($oauthConnection->properties && $oauthConnection->properties->get(self::NEXT_PAGE_TOKEN_KEY)) {
            SyncCards::dispatch($userId, 'google')->onQueue('sync-cards');
        }

        return true;
    }
}
