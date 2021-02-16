<?php

namespace App\Http\Controllers;

use andreskrey\Readability\ParseException;
use App\Http\Requests\Card\CreateCardRequest;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Jobs\GetContentFromScreenshot;
use App\Jobs\GetTags;
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
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
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
    private WebsiteExtractionHelper $websiteExtractionHelper;
    private WebsiteFactory $websiteFactory;

    public function __construct(
        CardRepository $cardRepo,
        CardTypeRepository $cardTypeRepository,
        CardSyncRepository $cardSyncRepository,
        WebsiteFactory $websiteFactory,
        WebsiteExtractionHelper $websiteExtractionHelper,
        OauthIntegrationService $oauthIntegrationService
    ) {
        $this->cardRepository = $cardRepo;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->cardSyncRepository = $cardSyncRepository;
        $this->websiteFactory = $websiteFactory;
        $this->websiteExtractionHelper = $websiteExtractionHelper;
        $this->oauthIntegrationService = $oauthIntegrationService;
    }

    /**
     * With some given raw html make an educated guess as to whether or not the user is currently viewing that page
     * as an authenticated user. If the user is authenticated in some way, we'll need to take a full screenshot,
     * as the only way to access the content of that page is in our chrome extension.
     *
     * @throws Exception
     */
    public function checkAuthed(Request $request): JsonResponse
    {
        $isAuthed = false;
        try {
            $fetchedWebsite = $this->websiteExtractionHelper->simpleFetch($request->get('url'))->parseContent();
        } catch (WebsiteNotFound | ParseException | Exception $exception) {
            // If you get a 404 that's pretty odd - the user is literally sending it FROM that url
            // So we're going to just say if curl 404's you ARE likely authed. Same thing can be said if
            // readability cannot parse the text - it's likely an authed page
            $isAuthed = true;
        }

        if (! $isAuthed && isset($fetchedWebsite)) {
            $rawHtmlWebsite = $this->websiteFactory->make($request->get('rawHtml'))->parseContent();
            $percentSimilar = 0;
            similar_text($rawHtmlWebsite->getContent(), $fetchedWebsite->getContent(), $percentSimilar);
            if ($percentSimilar < 80) {
                $isAuthed = true;
            }
        }

        return response()->json([
          'data' => [
              'isAuthed' => $isAuthed,
          ],
        ]);
    }

    /**
     * @throws Exception
     */
    public function create(CreateCardRequest $request): JsonResponse
    {
        $user = $request->user();

        $cardTypeKey = $request->get('card_type') ?? 'link';
        $cardType = $this->cardTypeRepository->firstOrCreate($cardTypeKey);

        $website = $this->websiteFactory->make(null);
        if ('link' === $cardTypeKey && $request->get('rawHtml')) {
            $website = $this->websiteFactory->make($request->get('rawHtml'))->parseContent();
        }

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

        if (('link' === $cardTypeKey && $request->get('rawHtml'))) {
            $this->cardRepository->setProperties($card, [
                'should_sync' => false,
            ]);
            $card->save();
        }

        if (
            // If we were sent the raw html we will likely get the picture, content, title, and description from it
            // No need to get anything with curl or puppeteer
            ('link' === $cardTypeKey && ! $request->get('rawHtml')) &&
            $this->oauthIntegrationService->isIntegrationValid($cardTypeKey) &&
            (! $request->get('image') || ! $request->get('content') || ! $request->get('title'))
        ) {
            SaveCardDataInitial::dispatch($card, $cardTypeKey)->onQueue('save-card-data-initial');
        }

        $withTags = true;
        if (false === $request->get('withTags')) {
            $withTags = false;
        }

        // We likely have the content (we may not be curling/puppeteer) so we have to get tags here
        if (
            $withTags &&
            (($request->get('rawHtml') && 'link' === $cardTypeKey) || $request->get('content'))
        ) {
            GetTags::dispatch($card);
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
            // Don't map some fields
            if ('getContentFromScreenshot' === $field) {
                return false;
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

        if ($request->get('getContentFromScreenshot')) {
            GetContentFromScreenshot::dispatch($card);
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
