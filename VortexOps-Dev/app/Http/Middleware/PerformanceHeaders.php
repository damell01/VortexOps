<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PerformanceHeaders extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Cache static assets aggressively (1 year)
        if ($this->isStaticAsset($request->path())) {
            $response->header('Cache-Control', 'public, max-age=31536000, immutable');
        }

        // Cache HTML pages for 1 minute, allow stale while revalidate
        if ($response->getStatusCode() === 200 && $request->isMethod('GET') && !$request->wantsJson()) {
            $response->header('Cache-Control', 'public, max-age=60, s-maxage=60');
        }

        // Enable compression
        $response->header('Accept-Encoding', 'gzip, deflate, br');

        // DNS prefetch for external resources
        $response->header('Link', '<https://fonts.googleapis.com>; rel=dns-prefetch', false);

        return $response;
    }

    private function isStaticAsset(string $path): bool
    {
        return preg_match('/\.(js|css|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|webp)$/i', $path);
    }
}
