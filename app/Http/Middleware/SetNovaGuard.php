<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Sets the guard to 'web' for Nova requests
 * Nova currently defaults to the "default" guard with no way to change it
 * As we use the api guard, we need to modify this for Nova routes
 * Used in routes.
 */
class SetNovaGuard
{
    public function handle($request, Closure $next)
    {
        Auth::shouldUse('web');

        return $next($request);
    }
}
