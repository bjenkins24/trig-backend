<?php

namespace App\Modules\Card\Integrations;

use App\Jobs\GetTags;
use App\Jobs\SaveCardData;
use App\Jobs\SaveCardDataInitial;
use App\Jobs\SaveThumbnails;
use App\Jobs\SyncCards as SyncCardsJob;
use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\CardSync\CardSyncRepository;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\Permission\PermissionRepository;
use App\Utils\ExtractDataHelper;
use Exception;
use Illuminate\Support\Collection;
use Throwable;

class SyncCards
{
    private string $integrationKey;
    private ContentInterface $contentIntegration;
    private IntegrationInterface $integration;
    private OauthConnectionRepository $oauthConnectionRepository;
    private OauthIntegrationService $oauthIntegrationService;
    private CardRepository $cardRepository;
    private CardSyncRepository $cardSyncRepository;
    private CardTypeRepository $cardTypeRepository;
    private LinkShareSettingRepository $linkShareSettingRepository;
    private PermissionRepository $permissionRepository;
    private ThumbnailHelper $thumbnailHelper;

    public function __construct(
        OauthConnectionRepository $oauthConnectionRepository,
        CardRepository $cardRepository,
        CardSyncRepository $cardSyncRepository,
        CardTypeRepository $cardTypeRepository,
        OauthIntegrationService $oauthIntegrationService,
        LinkShareSettingRepository $linkShareSettingRepository,
        PermissionRepository $permissionRepository,
        ThumbnailHelper $thumbnailHelper
    ) {
        $this->oauthConnectionRepository = $oauthConnectionRepository;
        $this->oauthIntegrationService = $oauthIntegrationService;
        $this->cardRepository = $cardRepository;
        $this->cardSyncRepository = $cardSyncRepository;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->linkShareSettingRepository = $linkShareSettingRepository;
        $this->permissionRepository = $permissionRepository;
        $this->thumbnailHelper = $thumbnailHelper;
    }

    public function setIntegration(IntegrationInterface $integration, ContentInterface $contentIntegration): void
    {
        $this->integration = $integration;
        $this->contentIntegration = $contentIntegration;
        $this->integrationKey = $integration::getIntegrationKey();
    }

    private function savePermissions(Collection $data, Card $card): bool
    {
        // Remove permissions first so the sync isn't creating duplicates if we're just updating
        $this->cardRepository->removeAllPermissions($card);

        if (0 === $data->get('users')->count() && 0 === $data->get('link_share')->count()) {
            return false;
        }
        $data->get('users')->each(function ($user) use ($card) {
            if ($user->get('id')) {
                // Create user permission
                return;
            }
            if ($user->get('email')) {
                $this->permissionRepository->createEmail($card, $user->get('capability'), $user->get('email'));

                return;
            }
        });
        $data->get('link_share')->each(function ($linkShare) use ($card) {
            switch ($linkShare->get('type')) {
                    case 'public':
                        $this->linkShareSettingRepository->createPublicIfNew($card, $linkShare->get('capability'));
                        break;
                    case 'anyone':
                        $this->linkShareSettingRepository->createAnyoneIfNew($card, $linkShare->get('capability'));
                        break;
                    case 'anyone_workspace':
                        $this->linkShareSettingRepository->createAnyoneWorkspaceIfNew($card, $linkShare->get('capability'));
                        break;
                }
        });

        return true;
    }

    /**
     * @throws CardIntegrationCreationValidate
     * @throws Exception
     */
    private function upsertCard(Collection $cardData): void
    {
        $data = $cardData->get('data');
        $existingCard = $this->cardRepository->getByForeignId($data->get('foreign_id'), $this->integrationKey);
        if ($existingCard) {
            if ($data->get('delete')) {
                $existingCard->delete();

                return;
            }

            if ($existingCard->actual_updated_at >= $data->get('actual_updated_at')) {
                return;
            }
        }

        // The card hasn't been created, but we will want to delete it, that means lets not create it to begin with
        if ($data->get('delete')) {
            return;
        }

        $cardType = $this->cardTypeRepository->firstOrCreate($data->get('card_type'));

        $card = $this->cardRepository->upsert([
            'actual_created_at'         => $data->get('actual_created_at'),
            'actual_updated_at'         => $data->get('actual_updated_at'),
            'card_type_id'              => $cardType->id,
            'description'               => $data->get('description'),
            'title'                     => $data->get('title'),
            'url'                       => $data->get('url'),
            'user_id'                   => $data->get('user_id'),
        ], $existingCard);

        if (! $card) {
            return;
        }

        if (! $existingCard && 'link' !== $this->integrationKey) {
            $this->cardRepository->createIntegration($card, $data->get('foreign_id'), $this->integrationKey);
        }

        if ($data->get('image') || $data->get('screenshot')) {
            SaveThumbnails::dispatch($data, $card);
        }
        $this->savePermissions($cardData->get('permissions'), $card);

        if (! ExtractDataHelper::isExcluded($data->get('card_type'))) {
            if ($existingCard) {
                SaveCardData::dispatch($card->id, $this->integrationKey)->onQueue('save-card-data');
            } else {
                SaveCardDataInitial::dispatch($card->id, $this->integrationKey)->onQueue('save-card-data-initial');
            }
        }
    }

    public function saveInitialCardData(Card $card): void
    {
        $data = $this->contentIntegration->getCardInitialData($card);
        if (! $data->isEmpty() && $data->get('title')) {
            $this->saveData($card, $data);
        }
        SaveCardData::dispatch($card->id, CardType::find($card->card_type_id)->name)->onQueue('save-card-data');
    }

    private function saveData(Card $card, Collection $data): bool
    {
        if ($data->get('image') || $data->get('screenshot')) {
            SaveThumbnails::dispatch($data, $card);
            $data->forget('image');
            $data->forget('screenshot');
        }

        // If we return any of these fields, we want to save them in full fledged columns not in properties
        $saveableFields = collect([
            'content',
            'title',
            'description',
        ]);

        $saveableFields->each(static function ($field) use ($card, $data) {
            $card->{$field} = $data->get($field);
            $data->forget($field);
        });

        $data = $data->reject(static function ($value) {
            return ! $value;
        });

        $card->setProperties($data->toArray());

        return $card->save();
    }

    /**
     * @throws Throwable
     */
    public function saveCardData(Card $card): bool
    {
        if (! $this->cardSyncRepository->shouldSync($card)) {
            return false;
        }
        $this->oauthIntegrationService->isIntegrationValid(CardType::find($card->card_type_id)->name);
        $cardIntegration = $this->cardRepository->getCardIntegration($card);
        $id = null;
        $mimeType = null;
        if ($cardIntegration) {
            $id = $cardIntegration->foreign_id;
            $mimeType = $this->cardRepository->getCardType($card)->name;
        }

        $data = $this->contentIntegration->getCardContentData($card, $id, $mimeType);

        // If there's no card content we should just stop. If this is in error, `getCardContentData` will do the
        // retry logic and logging. This can be a legitimate result of getCardContentData though, so we're just going
        // to no-op here instead of logging
        if ($data->isEmpty()) {
            return false;
        }

        // We need to know if it's synced in the PAST for deciding if we should get the tags for this card
        // We have to do this _before_ saving the card
        $shouldGetTags = $this->cardSyncRepository->shouldGetTags($card, $data->get('content'));

        $result = $this->saveData($card, $data);

        if ($result) {
            $this->cardSyncRepository->create([
                'card_id' => $card->id,
                // Successful sync
                'status'  => 1,
            ]);
        }

        if ($shouldGetTags) {
            GetTags::dispatch($card)->onQueue('get-tags');
        }

//        if ($card->content) {
//            CardDedupe::dispatch($card)->onQueue('card-dedupe');
//        }

        return true;
    }

    /**
     * If syncing is paginated then there will be a key in "service_next_page" token for the oauth connection
     * and we should continue.
     */
    private function syncNextPage(User $user, Workspace $workspace): bool
    {
        $nextPageToken = $this->oauthConnectionRepository->getNextPageToken($user, $workspace, $this->integrationKey);
        if (! $nextPageToken) {
            return false;
        }
        SyncCardsJob::dispatch($user->id, $this->integrationKey)->onQueue('sync-cards');

        return true;
    }

    /**
     * @throws CardIntegrationCreationValidate
     */
    public function syncCards(User $user, Workspace $workspace, ?int $since = null): bool
    {
        $cardData = collect($this->integration->getAllCardData($user, $workspace, $since))->recursive();
        if (0 === $cardData->count()) {
            return false;
        }

        $cardData->each(function ($card) {
            $this->upsertCard($card);
        });

        $this->syncNextPage($user, $workspace);

        return true;
    }
}
