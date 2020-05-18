<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Modules\Card\CardRepository;
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

    private CardRepository $cardRepo;

    public function __construct(CardRepository $cardRepo)
    {
        $this->cardRepo = $cardRepo;
    }

    public function get(Request $request, ?string $queryConstraints = null)
    {
        $cards = $this->cardRepo->searchCards($request->user(), $queryConstraints);

        return response()->json(['data' => $cards]);
    }
}
