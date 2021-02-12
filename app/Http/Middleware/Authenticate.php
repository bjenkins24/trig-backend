<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param Request $request
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }

    public function handle($request, Closure $next, ...$guards)
    {
        $accessToken = $request->cookie('access_token');
        if ($accessToken) {
            $request->headers->set('Authorization', 'Bearer '.$accessToken);
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }
}
