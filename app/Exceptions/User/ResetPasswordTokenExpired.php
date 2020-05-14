<?php

namespace App\Exceptions\User;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetPasswordTokenExpired extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'reset_password_token_expired',
            'message' => 'The password reset link has expired.',
        ], 400);
    }
}
