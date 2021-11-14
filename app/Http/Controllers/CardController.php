<?php

namespace App\Http\Controllers;

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
use App\Modules\Card\Integrations\Link\LinkIntegration;
use App\Modules\Card\Integrations\Twitter\Bookmarks;
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
    private LinkIntegration $linkIntegration;
    private Bookmarks $twitterBookmarks;

    public function __construct(
        CardRepository $cardRepo,
        CardTypeRepository $cardTypeRepository,
        CardSyncRepository $cardSyncRepository,
        WebsiteFactory $websiteFactory,
        OauthIntegrationService $oauthIntegrationService,
        LinkIntegration $linkIntegration,
        Bookmarks $twitterBookmarks
    ) {
        $this->cardRepository = $cardRepo;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->cardSyncRepository = $cardSyncRepository;
        $this->websiteFactory = $websiteFactory;
        $this->oauthIntegrationService = $oauthIntegrationService;
        $this->linkIntegration = $linkIntegration;
        $this->twitterBookmarks = $twitterBookmarks;
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
        return response()->json([
          'data' => [
              'isAuthed' => $this->linkIntegration->checkAuthed($request->get('url'), $request->get('rawHtml')),
          ],
        ]);
    }

    public function twitterBookmarks(Request $request): JsonResponse
    {
        $this->twitterBookmarks->saveTweetsFromArrayOfHtml($request->toArray(), $request->user());

        return response()->json('', 204);
    }

    public function create(CreateCardRequest $request): JsonResponse
    {
        ini_set('memory_limit', '2048m');
        ini_set('max_execution_time', '120');
        ini_set('max_input_time', '120');
        $user = $request->user();

        $cardTypeKey = $request->get('card_type') ?? 'link';
        $cardType = $this->cardTypeRepository->firstOrCreate($cardTypeKey);

        $website = $this->websiteFactory->make(null);
        if ('link' === $cardTypeKey && $request->get('rawHtml')) {
            $website = $this->websiteFactory->make($request->get('rawHtml'))->parseContent($request->url);
        }

        try {
            $card = $this->cardRepository->upsert([
                'card_type_id'      => $cardType->id,
                'user_id'           => $user->id,
                // Don't use get here getting it with url will get it with the protocol if it didn't already exist
                'url'               => $request->url,
                'title'             => $request->get('title') ?? $website->getTitle() ?? $request->get('url'),
                'description'       => $request->get('description') ?? $website->getExcerpt(),
                'content'           => $request->get('content') ?? $website->getContent(),
                'actual_created_at' => $request->get('created_at'),
                'actual_updated_at' => $request->get('updated_at'),
                'image'             => $request->get('image') ?? $website->getImage(),
                'screenshot'        => $request->get('screenshot'),
                'favorited'         => $request->get('is_favorited'),
                'collections'       => $request->get('collections'),
            ], null, $request->get('getContentFromScreenshot'));
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

        $isAuthed = false;
        if ('link' === $cardTypeKey && $request->get('rawHtml')) {
            $isAuthed = $this->linkIntegration->checkAuthed($request->get('url'), $request->get('rawHtml'));
        }

        if ($isAuthed && $request->get('rawHtml')) {
            // There's a race condition here. When the upsert method above is running it could be saving thumbnails in
            // a queue those thumbnails could save before this actually runs. If that's the case it will still have the
            // old card with the old properties and should_sync would overwrite the changes made to `properties`
            // by the save thumbnail
            $card = Card::find($card->id);
            $card->setProperties(['should_sync' => false]);
            $card->save();
        }

        if (
            // If we were sent the raw html we will likely get the picture, content, title, and description from it
            // No need to get anything with curl or puppeteer
            ! $request->get('getContentFromScreenshot') &&
            ! $request->get('rawHtml') &&
            $this->oauthIntegrationService->isIntegrationValid($cardTypeKey) &&
            (! $request->get('image') || ! $request->get('content') || ! $request->get('title'))
        ) {
            SaveCardDataInitial::dispatch($card->id, $cardTypeKey)->onQueue('save-card-data-initial');
        }

        // If we have rawHtml then we have the content and we're NOT going to do the initial data sync. So let's just
        // get the tags right away
        if ($request->get('rawHtml') && ! $request->get('getContentFromScreenshot')) {
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
            ], 404);
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

    public function saveView(Request $request): JsonResponse
    {
        $card = $this->cardRepository->getCardWithToken($request->get('token'));

        if (! $card) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The card you are trying to update does not exist. Check the id and try again.',
            ], 404);
        }

        $saved = $this->cardRepository->saveView($card, $request->user('api'));

        if (! $saved) {
            return response()->json([
                'error'   => 'unexpected',
                'message' => 'Something went wrong. The card view was not saved.',
            ], 500);
        }

        if ($this->cardSyncRepository->shouldSync($card)) {
            SaveCardData::dispatch($card->id, CardType::find($card->card_type_id)->name)->onQueue('save-card-data');
        }

        return response()->json([], 204);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function update(UpdateCardRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $card = Card::find($id);

        if (! $card) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The card you are trying to update does not exist. Check the id and try again.',
            ], 404);
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
            SaveCardData::dispatch($card->id, CardType::find($card->card_type_id)->name)->onQueue('save-card-data');
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
            ], 404);
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
        $results = $this->cardRepository->searchCards($request->user('api'), collect($request->all()));

        return response()->json([
            'data'    => $results->get('cards'),
            'meta'    => $results->get('meta'),
            'filters' => $results->get('filters'),
        ]);
    }
}
