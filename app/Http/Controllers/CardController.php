<?php

namespace App\Http\Controllers;

use App\Http\Requests\Card\CreateCardRequest;
use App\Jobs\SaveCardData;
use App\Modules\Card\CardRepository;
use App\Modules\CardType\CardTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    // public function get(Request $request, ?string $queryConstraints = null)
    // {
    //     $user = $request->user();
    //     $organization = $user->organizations()->first();
    //     $users = $organization->users()->pluck('users.id');
    //     $cards = Card::whereIn('user_id', $users)
    //         ->select('id', 'user_id', 'title', 'card_type_id', 'image', 'actual_created_at', 'url')
    //         ->with(['user:id,first_name,last_name,email'])
    //         ->orderBy('actual_created_at', 'desc')
    //         ->paginate(25);
    //     $cards = parse_str($queryConstraints, $queryConstraints);
    //     collect($queryConstraints);

    //     return response()->json(['data' => $cards]);
    // }

    private CardRepository $cardRepository;

    public function __construct(
        CardRepository $cardRepo,
        CardTypeRepository $cardTypeRepository
    ) {
        $this->cardRepository = $cardRepo;
        $this->cardTypeRepository = $cardTypeRepository;
    }

    public function get(Request $request, ?string $queryConstraints = null)
    {
        $cards = $this->cardRepository->searchCards($request->user(), $queryConstraints);

        return response()->json(['data' => $cards]);
    }

    public function updateOrInsert(CreateCardRequest $request): JsonResponse
    {
        $user = $request->user();

        $cardType = $this->cardTypeRepository->firstOrCreate('link');
        $card = $this->cardRepository->updateOrInsert([
            'url'            => $request->get('url'),
            'title'          => $request->get('url'),
            'card_type_id'   => $cardType->id,
            'user_id'        => $user->id,
        ]);

        if (! $card) {
            return response()->json([
                'error'   => 'unexpected',
                'message' => 'An unexpected error has occurred. The card was not saved',
            ]);
        }

        SaveCardData::dispatch($card, 'link');

        return response()->json([
            'data' => $card,
        ]);
    }
}
