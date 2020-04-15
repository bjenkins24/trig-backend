<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CardController extends Controller
{
    public function get(Request $request)
    {
        $user = $request->user();
        $cards = $user->cards()->get();

        return response()->json(['data' => $cards->toArray()]);
    }
}
