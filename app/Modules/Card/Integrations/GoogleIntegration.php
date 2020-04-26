<?php

namespace App\Modules\Card\Integrations;

use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\Permission\PermissionRepository;
use App\Utils\FileHelper;
use Exception;
use Google_Service_Directory as GoogleServiceDirectory;
use Google_Service_Drive as GoogleServiceDrive;
use Illuminate\Support\Collection;

class GoogleIntegration implements IntegrationInterface
{
    const IMAGE_PATH = 'public/card-thumbnails';
    const PAGE_SIZE = 10;

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
    private $oauthConnection;
    private $card;

    public function __construct(OauthConnectionService $oauthConnection)
    {
        $this->oauthConnection = $oauthConnection;
    }

    private function setClient(User $user)
    {
        $this->client = $this->oauthConnection->getClient($user, GoogleConnection::getKey());
    }

    public function getFiles(User $user): Collection
    {
        if (! $this->client) {
            $this->setClient($user);
        }
        $service = new GoogleServiceDrive($this->client);

        $optParams = [
            'pageSize' => self::PAGE_SIZE,
            'fields'   => 'nextPageToken, files',
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
        $accessToken = $this->oauthConnection->getAccessToken($user, GoogleConnection::getKey());
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
        $result = \Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail'));
        if ($result) {
            $card->image = \Config::get('app.url').\Storage::url($imagePathWithExtension);
            $card = $card->save();
        }
    }

    public function savePermissions(User $user, Card $card, $file): void
    {
        $linkShareRepo = app(LinkShareSettingRepository::class);
        $permissions = collect($file->permissions);
        $permissionRepo = app(PermissionRepository::class);
        $permissions->each(function ($permission) use ($card) {
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

    private function createCard(User $user, $file, CardType $cardType): void
    {
        $card = $user->cards()->create([
            'card_type_id'              => $cardType->id,
            'title'                     => $file->name,
            'actual_created_at'         => $file->createdTime,
            'actual_modified_at'        => $file->modifiedTime,
            'description'               => $file->description,
            'url'                       => $file->webViewLink,
        ]);
        if (! $card || $file->trashed) {
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
    public function getDomains(User $user): Collection
    {
        if (! $this->client) {
            $this->setClient($user);
        }
        $service = new GoogleServiceDirectory($this->client);
        // my_customer get's the domains for the current customer which is what we want
        // weird API, but that's 100% Google
        return collect($service->domains->listDomains('my_customer')->domains);
    }

    public function syncDomains($user)
    {
        $domains = $this->getDomains($user);
        $properties = ['google_domains' => []];
        foreach ($domains as $domain) {
            $properties['google_domains'][] = [$domain->domainName => true];
        }
        $user->properties = $properties;
        $user->save();
    }

    /**
     * Sync cards from google.
     *
     * @return void
     */
    public function syncCards(User $user)
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
