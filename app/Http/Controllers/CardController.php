<?php

namespace App\Http\Controllers;

use App\Http\Requests\Card\CreateCardRequest;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Jobs\GetContentFromImage;
use App\Jobs\SaveCardData;
use App\Jobs\SaveCardDataInitial;
use App\Models\Card;
use App\Models\CardType;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardExists;
use App\Modules\Card\Exceptions\CardUserIdMustExist;
use App\Modules\Card\Exceptions\CardWorkspaceIdMustExist;
use App\Modules\CardSync\CardSyncRepository;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Utils\WebsiteExtraction\WebsiteFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class CardController extends Controller
{
    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;
    private CardSyncRepository $cardSyncRepository;
    private OauthIntegrationService $oauthIntegrationService;
    private WebsiteFactory $websiteFactory;

    public function __construct(
        CardRepository $cardRepo,
        CardTypeRepository $cardTypeRepository,
        CardSyncRepository $cardSyncRepository,
        WebsiteFactory $websiteFactory,
        OauthIntegrationService $oauthIntegrationService
    ) {
        $this->cardRepository = $cardRepo;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->cardSyncRepository = $cardSyncRepository;
        $this->websiteFactory = $websiteFactory;
        $this->oauthIntegrationService = $oauthIntegrationService;
    }

    /**
     * @throws Exception
     */
    public function create(CreateCardRequest $request): JsonResponse
    {
        $user = $request->user();

        $cardTypeKey = $request->get('card_type') ?? 'link';
        $cardType = $this->cardTypeRepository->firstOrCreate($cardTypeKey);

        $website = $this->websiteFactory->make($request->get('rawHtml'))->parseContent();

        try {
            $card = $this->cardRepository->upsert([
                'card_type_id'      => $cardType->id,
                'user_id'           => $user->id,
                'url'               => $request->url,
                'title'             => $request->get('title') ?? $website->getTitle() ?? $request->get('url'),
                'description'       => $request->get('description') ?? $website->getExcerpt(),
                'content'           => $request->get('content') ?? $website->getContent(),
                'actual_created_at' => $request->get('created_at'),
                'actual_updated_at' => $request->get('updated_at'),
                'image'             => $request->get('image') ?? $website->getImage(),
                'screenshot'        => $request->get('screenshot'),
                'favorited'         => $request->get('is_favorited'),
            ]);
        } catch (CardUserIdMustExist | CardWorkspaceIdMustExist $exception) {
            return response()->json([
                'error'   => 'bad_request',
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (! $card) {
            return response()->json([
                'error'   => 'unexpected',
                'message' => 'An unexpected error has occurred. The card was not saved',
            ]);
        }

        $getContentFromImage = $request->get('getContentFromImage');
        if (! $getContentFromImage && $this->oauthIntegrationService->isIntegrationValid($cardTypeKey)) {
            SaveCardDataInitial::dispatch($card, $cardTypeKey)->onQueue('save-card-data-initial');
        }

        if ($getContentFromImage) {
            GetContentFromImage::dispatch($card)->onQueue('save-card-data');
        }

        return response()->json([
            'data' => $this->cardRepository->mapToFields($card),
        ]);
    }

    public function get(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $card = Card::find($id);

        if (! $card) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The card you requested does not exist. Check the id and try again.',
            ], 400);
        }

        if ((int) $card->user_id !== $user->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The card you requested could not be retrieved because you do not have permission to access it.',
            ], 403);
        }

        // TODO: make the get request match the params in getAll and update
        return response()->json([
            'data' => $card,
        ]);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function update(UpdateCardRequest $request): JsonResponse
    {
        $user = $request->user();

        $card = Card::find($request->get('id'));

        if (! $card) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The card you are trying to update does not exist. Check the id and try again.',
            ], 400);
        }

        if ((int) $card->user_id !== $user->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The card was not updated because you do not have permission to update it.',
            ], 403);
        }

        $fields = collect(json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR));
        $data = [];
        // Map fields to DB fields
        $fields->each(static function ($fieldValue, $field) use (&$data) {
            if ('createdAt' === $field) {
                return $data['actual_created_at'] = $fieldValue;
            }
            if ('updatedAt' === $field) {
                return $data['actual_updated_at'] = $fieldValue;
            }

            return $data[$field] = $fieldValue;
        });

        try {
            $card = $this->cardRepository->upsert(
                array_merge(['user_id' => $user->id], $data),
                $card
            );
        } catch (CardExists $exception) {
            return response()->json([
                'error'   => 'exists',
                'message' => $exception->getMessage(),
            ], 409);
        }

        if ($this->cardSyncRepository->shouldSync($card)) {
            SaveCardData::dispatch($card, CardType::find($card->card_type_id)->name)->onQueue('save-card-data');
        }

        return response()->json([], 204);
    }

    /**
     * @throws Exception
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $card = Card::find($id);

        if (! $card) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The card you are trying to delete does not exist. Check the id and try again.',
            ], 400);
        }

        if ((int) $card->user_id !== $user->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The card was not deleted because you do not have permission to update it.',
            ], 403);
        }

        $card->delete();

        return response()->json([], 204);
    }

    public function getAll(Request $request)
    {
        $results = $this->cardRepository->searchCards($request->user(), collect($request->all()));

        return response()->json([
            'data'    => $results->get('cards'),
            'meta'    => $results->get('meta'),
            'filters' => $results->get('filters'),
        ]);
    }
}
