<?php

namespace App\Exceptions\User;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FailedGoogleSso extends Exception
{
    /**
     * Report or log an exception.
     *
     * @throws \Exception
     */
    public function report(): void
    {
        \Log::notice('Unable to SSO user. Either Google is down or possibly a malicious user posted an invalid auth code.');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'auth_failed',
            'message' => 'Something went wrong. You were not able to be authenticated',
        ]);
    }
}
