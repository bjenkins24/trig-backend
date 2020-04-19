<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoAccessTokenSet extends Exception
{
    /**
     * Report or log an exception.
     *
     * @throws \Exception
     */
    public function report(): void
    {
        \Log::notice('A user tried to log in, they were authenticated, but the access token was not set');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'no_access_token',
            'message' => 'Something went wrong. You were not able to be authenticated',
        ]);
    }
}
