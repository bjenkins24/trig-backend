<?php

namespace App\Http\Controllers;

use App\Http\Requests\Card\CreateCardRequest;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Models\CardType;
use App\Modules\Card\CardRepository;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;

class CardController extends Controller
{
//     public function get(Request $request, ?string $queryConstraints = null)
//     {
//         $user = $request->user();
//         $organization = $user->organizations()->first();
//         $users = $organization->users()->pluck('users.id');
//         $cards = Card::whereIn('user_id', $users)
//             ->select('id', 'user_id', 'title', 'card_type_id', 'image', 'actual_created_at', 'url')
//             ->with(['user:id,first_name,last_name,email'])
//             ->orderBy('actual_created_at', 'desc')
//             ->paginate(25);
//         $cards = parse_str($queryConstraints, $queryConstraints);
//         collect($queryConstraints);
//
//         return response()->json(['data' => $cards]);
//     }

    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;
    private OauthIntegrationService $oauthIntegrationService;

    public function __construct(
        CardRepository $cardRepo,
        CardTypeRepository $cardTypeRepository,
        OauthIntegrationService $oauthIntegrationService
    ) {
        $this->cardRepository = $cardRepo;
        $this->cardTypeRepository = $cardTypeRepository;
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

        $card = $this->cardRepository->updateOrInsert([
            'card_type_id'        => $cardType->id,
            'user_id'             => $user->id,
            'url'                 => $request->url,
            'title'               => $request->get('title') ?? $request->get('url'),
            'description'         => $request->get('description'),
            'content'             => $request->get('content'),
            'actual_created_at'   => $request->get('createdAt'),
            'actual_updated_at'   => $request->get('updatedAt'),
            'image'               => $request->get('image'),
            'favorited'           => $request->get('isFavorited'),
        ]);

        if (! $card) {
            return response()->json([
                'error'   => 'unexpected',
                'message' => 'An unexpected error has occurred. The card was not saved',
            ]);
        }

        if ($this->oauthIntegrationService->isIntegrationValid($cardTypeKey)) {
            SaveCardData::dispatch($card, $cardTypeKey);
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

        $card = $this->cardRepository->updateOrInsert(
            array_merge(['user_id' => $user->id], $data),
            $card
        );

        $cardType = CardType::find($card->card_type_id)->name;
        if ($this->oauthIntegrationService->isIntegrationValid($cardType)) {
            SaveCardData::dispatch($card, $cardType);
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
        $cards = $this->cardRepository->searchCards($request->user(), collect($request->all()));

        return response()->json(['data' => $cards]);
    }
}
