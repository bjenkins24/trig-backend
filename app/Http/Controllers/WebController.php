<?php

namespace App\Http\Controllers;

use Exception;

class WebController extends Controller
{
    public function home()
    {
        return view('welcome');
    }

    public function debug(): void
    {
        throw new Exception('My first Sentry error!');
    }

    public function health()
    {
        echo 'SUCCESS';

        return;
    }

    public function fallback()
    {
        return response()->json([
            'message' => 'Page Not Found. If the error persists, contact support@trytrig.com', ], 404);
    }
}
