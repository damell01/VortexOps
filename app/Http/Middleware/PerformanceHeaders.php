<?php

namespace App\Http\Middleware;

use App\Support\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceHeaders
{
    /**
     * Which page and button a slow request came from.
     *
     * Every button press on a Livewire page arrives as livewire.update, so
     * the route alone could not tell a slow scan from a slow save. The page
     * it was pressed on and the component and method it called can, and are
     * only worked out when a request is being logged as slow.
     */
    private function whatWasPressed(Request $request): array
    {
        if (! $request->is('livewire*')) {
            return ['path' => '/'.ltrim($request->path(), '/')];
        }

        $calls = [];
        foreach ((array) $request->input('components', []) as $component) {
            $name = json_decode((string) ($component['snapshot'] ?? ''), true)['memo']['name'] ?? '?';
            $methods = array_column((array) ($component['calls'] ?? []), 'method');
            $calls[] = class_basename(str_replace('.', '\\', $name)).($methods ? '@'.implode(',', $methods) : '');
        }

        return [
            'page' => parse_url((string) $request->headers->get('referer'), PHP_URL_PATH),
            'calls' => implode(' | ', array_slice($calls, 0, 5)),
        ];
    }

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
                ] + $this->whatWasPressed($request));
            }
        }
    }
}
