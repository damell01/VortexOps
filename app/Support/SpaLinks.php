<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Paths under /admin that are not panel pages (exports, PDFs, JSON feeds,
 * downloads): links to these must load normally, never through SPA
 * navigation. Built from the route table, so a new download route is
 * excluded without anyone remembering to list it.
 */
final class SpaLinks
{
    private static ?array $memo = null;

    /** @return list<string> regular expressions matched against the path without its leading slash */
    public static function excluded(): array
    {
        return self::$memo ??= collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                $uri = $route->uri();
                $name = (string) $route->getName();

                return str_starts_with($uri, 'admin/') && ! str_starts_with($name, 'filament.') && ! str_starts_with($name, 'livewire.')
                    && in_array('GET', $route->methods(), true);
            })
            ->map(fn ($route) => '^' . preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($route->uri(), '/')) . '$')
            ->unique()->values()->all();
    }
}
