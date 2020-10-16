<?php

namespace App\Modules\Card\Integrations;

use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\OauthConnection\Exceptions\OauthKeyInvalid;
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

    private OauthIntegration $integration;
    private string $integrationKey;
    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;
    private Collection $cardData;
    private LinkShareSettingRepository $linkShareSettingRepository;
    private PermissionRepository $permissionRepository;

    public function __construct(
        OauthIntegration $integration,
        CardRepository $cardRepository,
        CardTypeRepository $cardTypeRepository,
        LinkShareSettingRepository $linkShareSettingRepository,
        PermissionRepository $permissionRepository
    ) {
        $this->integration = $integration;
        $this->integrationKey = $integration->getKey();
        $this->cardRepository = $cardRepository;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->cardData = $this->integration->getCardData();
        $this->linkShareSettingRepository = $linkShareSettingRepository;
        $this->permissionRepository = $permissionRepository;
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
     * @throws OauthKeyInvalid
     * @throws CardIntegrationCreationValidate
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

    /**
     * @throws CardIntegrationCreationValidate
     * @throws OauthKeyInvalid
     */
    public function syncCards(int $userId, ?int $since = null): bool
    {
        if (0 === $this->cardData->count) {
            return false;
        }

        $this->cardData->each(function ($card) {
            $this->upsertCard($card->get('data'));
        });

        return true;
    }

    new SyncCards($cardData);

//    // Abstracted don't need it everywhere
//    public function getCards() {
//        $data = $this->getData();
//        $cards = $data->each(static function($datum) {
//             $this->dataToCard($datum);
//        });
//        return $cards;
//    }
//
//
//    // EVERY CLASS
//    public function getAllData(): Data {
//        $allData = curl getData;
//        return collect($allData);
//    }
//
//    // Every class
//    public function dataToCard(Data $data) {
//        $card = Card->joe = $data['joe'];
//        return $card;
//    }
//
//    $card = [
//        'data' => [
//            'user_id' => 2,
//            'delete' => true,
//            'card_type' => amplitude,
//            'url' => '',
//            'foreign_id' => '',
//            'title' => '',
//            'description' => '',
//            'content' => '',
//            'actual_created_at' => '',
//            'actual_updated_at' => '',
//            'thumbnail_uri' => '',
//            'properties' => '',
//        ],
//        'permissions' => [
//            'users'    => [
//                [
//                    'email'      => 'brian@asd.com',
//                    'capability' => 'writer',
//                ],
//                [
//                    'id'         => 1,
//                    'capability' => 'reader',
//                ],
//            ],
//            'link_share' => [
//                [
//                    'type' => 'public',
//                    'cabability' => 'reader'
//                ],
//                [
//                    'type' => 'anyone_organization',
//                    'cabability' => 'reader'
//                ],
//                [
//                    'type' => 'anyone',
//                    'cabability' => 'reader'
//                ]
//            ]
//        ]
//    ];
//
//    // Every class
//    public function permissions?(Data $data) {
//
//    }
//
//    // Every class
//    public function dataToThumbnail?() {
//
//    }
}
