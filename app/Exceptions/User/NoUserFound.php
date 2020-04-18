<?php

namespace App\Exceptions\User;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoUserFound extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'no_user_found',
            'message' => 'There is no user with the given email. Please try again.',
        ]);
    }
}
