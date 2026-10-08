<?php

namespace App\Http\Middleware;

use App\Support\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $metrics = app(RequestMetrics::class);
        $metrics->active = true;
        $metrics->queries = 0;
        $metrics->databaseMs = 0;
        $started = hrtime(true);
        $response = null;
        try {
            $response = $next($request);
            return $response;
        } finally {
            $elapsed = (hrtime(true) - $started) / 1e6;
            $metrics->active = false;
            if ($response) {
                $response->headers->set('Server-Timing', sprintf('app;dur=%.1f, db;dur=%.1f', $elapsed, $metrics->databaseMs));
                $response->headers->set('X-Query-Count', (string) $metrics->queries);
                // Never expose signed-in HTML through a shared/public page cache.
                if ($request->user() || $request->is('admin*', 'livewire*')) {
                    $response->headers->set('Cache-Control', 'private, no-store');
                }
            }
            if ($elapsed >= 1500 || $metrics->databaseMs >= 500 || $metrics->queries >= 100 || ! $response || $response->getStatusCode() >= 500) {
                Log::warning('Slow or failed web request', [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'status' => $response?->getStatusCode() ?? 500,
                    'duration_ms' => round($elapsed, 1),
                    'database_ms' => round($metrics->databaseMs, 1),
                    'queries' => $metrics->queries,
                ]);
            }
        }
    }
}
