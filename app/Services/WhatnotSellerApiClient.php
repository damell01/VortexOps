<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only Whatnot API transport. No write endpoints, no automatic jobs.
 */
final class WhatnotSellerApiClient
{
    public function get(WhatnotApiConnection $connection, string $resource, array $query = []): Response
    {
        if (blank($connection->access_token)) {
            throw new RuntimeException('Whatnot API credentials are missing.');
        }

        $paths = [
            'shows' => '/shows',
            'orders' => '/orders',
        ];
        if (! isset($paths[$resource])) {
            throw new RuntimeException('Unsupported read-only Whatnot API resource.');
        }

        $base = rtrim((string) config('services.whatnot_seller.base_url'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || ($host !== 'whatnot.com' && ! str_ends_with($host, '.whatnot.com'))) {
            throw new RuntimeException('API host is not approved.');
        }
        if (! str_starts_with($base, 'https://')) {
            throw new RuntimeException('Whatnot API base URL must use HTTPS.');
        }

        return Http::withToken($connection->access_token)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, function ($error) {
                if ($error instanceof \Illuminate\Http\Client\RequestException) {
                    return $error->response->status() === 429 || $error->response->serverError();
                }
                return $error instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->get($base.$paths[$resource], $query);
    }
}
