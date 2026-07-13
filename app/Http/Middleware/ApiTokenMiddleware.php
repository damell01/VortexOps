<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('app.scraper_api_token');

        // Fail closed when no token is configured, and compare in constant time
        // so the token can't be recovered byte-by-byte via response timing.
        if (! $token || ! hash_equals((string) $token, (string) $request->bearerToken())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
