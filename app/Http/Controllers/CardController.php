<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function get(Request $request, ?string $queryConstraints = null)
    {
        $user = $request->user();
        $organization = $user->organizations()->first();
        $users = $organization->users()->pluck('users.id');
        $cards = Card::whereIn('user_id', $users)
            ->select('id', 'user_id', 'title', 'card_type_id', 'image', 'actual_created_at')
            ->with(['user:id,first_name,last_name,email', 'cardLink:id,card_id,link'])
            ->orderBy('actual_created_at', 'desc')
            ->paginate(25);
        $cards = parse_str($queryConstraints, $queryConstraints);
        $cards = $queryConstraints;

        return response()->json(['data' => $cards]);
    }
}
