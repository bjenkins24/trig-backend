<?php

namespace App\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Jobs\SaveCardData;
use App\Jobs\SyncCards as SyncCardsJob;
use App\Models\Card;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Exceptions\OauthKeyInvalid;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\Permission\PermissionRepository;
use App\Utils\ExtractDataHelper;
use App\Utils\FileHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncCards
{
    public const IMAGE_PATH = 'public/card-thumbnails';

    private string $integrationKey;
    private ContentInterface $contentIntegration;
    private IntegrationInterface $integration;
    private OauthConnectionRepository $oauthConnectionRepository;
    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;
    private LinkShareSettingRepository $linkShareSettingRepository;
    private PermissionRepository $permissionRepository;

    public function __construct(
        OauthConnectionRepository $oauthConnectionRepository,
        CardRepository $cardRepository,
        CardTypeRepository $cardTypeRepository,
        LinkShareSettingRepository $linkShareSettingRepository,
        PermissionRepository $permissionRepository
    ) {
        $this->oauthConnectionRepository = $oauthConnectionRepository;
        $this->cardRepository = $cardRepository;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->linkShareSettingRepository = $linkShareSettingRepository;
        $this->permissionRepository = $permissionRepository;
    }

    public function setIntegration(IntegrationInterface $integration, ContentInterface $contentIntegration): void
    {
        $this->integration = $integration;
        $this->contentIntegration = $contentIntegration;
        $this->integrationKey = $integration::getIntegrationKey();
    }

    public function getThumbnail(Collection $data): Collection
    {
        try {
            $thumbnail = file_get_contents($data->get('thumbnail_uri'));
        } catch (Exception $e) {
            Log::notice('Couldn\'t get a thumbnail: '.$data->get('thumbnail_uri').' - '.$e->getMessage());

            return collect([]);
        }

        $fileInfo = collect(getimagesizefromstring($thumbnail));

        if (! $fileInfo->has('mime')) {
            Log::notice('Couldn\'t get a thumbnail. It had no mime type: '.$data->get('thumbnail_uri'));

            return collect([]);
        }

        return collect([
            'thumbnail' => $thumbnail,
            'extension' => FileHelper::mimeToExtension($fileInfo->get('mime')),
            'width'     => $fileInfo->get(0),
            'height'    => $fileInfo->get(1),
        ]);
    }

    public function saveThumbnail(Collection $data, Card $card): bool
    {
        if (! $data->get('thumbnail_uri')) {
            return false;
        }
        $imagePath = self::IMAGE_PATH.'/'.$card->id;
        $thumbnail = $this->getThumbnail($data);
        if ($thumbnail->isEmpty()) {
            return false;
        }
        $imagePathWithExtension = $imagePath.'.'.$thumbnail->get('extension');
        $result = Storage::put($imagePathWithExtension, $thumbnail->get('thumbnail'));
        if ($result) {
            $card->image = Config::get('app.url').Storage::url($imagePathWithExtension);
            $card->image_width = $thumbnail->get('width');
            $card->image_height = $thumbnail->get('height');
            $card->save();
        }

        return true;
    }

    public function savePermissions(Collection $data, Card $card): void
    {
        // Remove permissions first so the sync isn't creating duplicates if we're just updating
        $this->cardRepository->removeAllPermissions($card);

        $data->get('permissions')->each(function ($permission) use ($card) {
            $permission->get('users')->each(function ($user) use ($card) {
                if ($user->get('id')) {
                    // Create user permission
                    return;
                }
                if ($user->get('email')) {
                    $this->permissionRepository->createEmail($card, $user->get('capability'), $user->get('email'));

                    return;
                }
            });
            $permission->get('link_share')->each(function ($linkShare) use ($card) {
                switch ($linkShare->get('type')) {
                    case 'public':
                        $this->linkShareSettingRepository->createPublicIfNew($card, $linkShare->get('capability'));
                        break;
                    case 'anyone':
                        $this->linkShareSettingRepository->createAnyoneIfNew($card, $linkShare->get('capability'));
                        break;
                    case 'anyone_organization':
                        $this->linkShareSettingRepository->createAnyoneOrganizationIfNew($card, $linkShare->get('capability'));
                        break;
                }
            });
        });
    }

    /**
     * @throws CardIntegrationCreationValidate
     * @throws OauthKeyInvalid
     * @throws Exception
     */
    public function upsertCard(Collection $data): ?Card
    {
        $existingCard = $this->cardRepository->getByForeignId($data->get('foreign_id'), $this->integrationKey);
        if ($existingCard) {
            if ($data->get('delete')) {
                $existingCard->delete();

                return null;
            }
            if ($existingCard->actual_modified_at >= $data->get('actual_modified_at')) {
                return null;
            }
        }

        $cardType = $this->cardTypeRepository->firstOrCreate($data->get('card_type'));

        $card = $this->cardRepository->updateOrInsert([
            'actual_created_at'         => $data->get('actual_created_at'),
            'actual_modified_at'        => $data->get('actual_modified_at'),
            'card_type_id'              => $cardType->id,
            'description'               => $data->get('description'),
            'title'                     => $data->get('title'),
            'url'                       => $data->get('url'),
            'user_id'                   => $data->get('user_id'),
        ], $existingCard);

        if (! $card) {
            return null;
        }

        if (! $existingCard) {
            $this->cardRepository->createIntegration($card, $data->get('foreign_id'), $this->integrationKey);
        }

        $this->saveThumbnail($data, $card);
        $this->savePermissions($data, $card);

        if (! ExtractDataHelper::isExcluded($data->get('card_type'))) {
            SaveCardData::dispatch($card, $this->integrationKey)->onQueue('card-data');
        }
    }

    public function saveCardData(Card $card): void
    {
        $cardIntegration = $this->cardRepository->getCardIntegration($card);
        if (! $cardIntegration) {
            return;
        }
        $id = $cardIntegration->foreign_id;
        $mimeType = $this->cardRepository->getCardType($card)->name;

        $content = $this->contentIntegration->getCardContent($card, $id, $mimeType);
        $data = app(ExtractDataHelper::class)->getFileData($mimeType, $content);

        // Save the card data retrieved from the extraction
        $card->content = $data->get('content');
        $data->forget('content');
        $data = $data->reject(static function ($value) {
            return ! $value;
        });
        $card->properties = $data->toArray();
        $card->save();

        if ($card->content) {
            CardDedupe::dispatch($card)->onQueue('card-dedupe');
        }
    }

    /**
     * If syncing is paginated then there will be a key in "service_next_page" token for the oauth connection
     * and we should continue.
     */
    private function syncNextPage(User $user): bool
    {
        $nextPageToken = $this->oauthConnectionRepository->getNextPageToken($user, $this->integrationKey);
        if (! $nextPageToken) {
            return false;
        }
        SyncCardsJob::dispatch($user->id, $this->integrationKey)->onQueue('sync-cards');

        return true;
    }

    /**
     * @throws CardIntegrationCreationValidate
     * @throws OauthKeyInvalid
     */
    public function syncCards(User $user, ?int $since = null): void
    {
        $cardData = collect($this->integration->getAllCardData($user, $since))->recursive();
        if (0 === $cardData->count) {
            return;
        }

        $cardData->each(function ($card) {
            $this->upsertCard($card->get('data'));
        });

        $this->syncNextPage($user);
    }
}
