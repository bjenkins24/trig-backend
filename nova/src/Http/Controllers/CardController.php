<?php

namespace Laravel\Nova\Http\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Nova\Http\Requests\CardRequest;

class CardController extends Controller
{
    /**
     * List the cards for the given resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(CardRequest $request)
    {
        return $request->availableCards();
    }
}
