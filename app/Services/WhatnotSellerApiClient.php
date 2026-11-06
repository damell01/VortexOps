<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Whatnot Seller API transport (REST v2, https://developers.whatnot.com).
 *
 * Read endpoints only, plus POST /reports — generating a report is how the
 * financial ledger (tips, earnings, refunds) is read, and needs write:reports.
 * Nothing here changes orders, inventory, shipments or products on Whatnot.
 *
 * Rate limits (developers.whatnot.com/getting-started/rate-limits): 10
 * requests/second per seller per app. A 429 carries Retry-After; wait at least
 * that long, then back off exponentially with jitter. RateLimit-Remaining is a
 * hint used to slow down before being rejected.
 */
class WhatnotSellerApiClient
{
    /** Whatnot's documented page-size maximum for every list endpoint. */
    public const MAX_PAGE_SIZE = 50;

    private const MAX_ATTEMPTS = 5;
    private const MAX_WAIT_SECONDS = 30;

    /** Endpoints this app may call: name => [method, path template]. */
    private const ENDPOINTS = [
        'me'             => ['GET', '/users/me'],
        'shows'          => ['GET', '/shows'],
        'show'           => ['GET', '/shows/{id}'],
        'show_analytics' => ['GET', '/analytics/shows/{id}'],
        'orders'         => ['GET', '/orders'],
        'order'          => ['GET', '/orders/{id}'],
        'order_items'    => ['GET', '/orders/{id}/items'],
        'shipments'      => ['GET', '/shipments'],
        'shipment_items' => ['GET', '/shipments/{id}/items'],
        'reports'        => ['GET', '/reports'],
        'report'         => ['GET', '/reports/{id}'],
        'generate_report' => ['POST', '/reports'],
    ];

    /** @var callable(float):void */
    private $sleeper;

    public function __construct(?callable $sleeper = null)
    {
        $this->sleeper = $sleeper ?? fn (float $seconds) => usleep((int) round($seconds * 1_000_000));
    }

    /**
     * Back-compatible list call for 'shows', 'orders' and 'reports'.
     */
    public function get(WhatnotApiConnection $connection, string $resource, array $query = []): Response
    {
        if (! in_array($resource, ['shows', 'orders', 'reports'], true)) {
            throw new RuntimeException('Unsupported read-only Whatnot API resource.');
        }

        return $this->call($connection, $resource, query: $query);
    }

    /**
     * Call one of the allowed endpoints, honouring Whatnot's rate limits.
     */
    public function call(WhatnotApiConnection $connection, string $endpoint, ?string $id = null, array $query = [], ?array $body = null): Response
    {
        [$method, $template] = self::ENDPOINTS[$endpoint] ?? throw new RuntimeException('Unsupported Whatnot API endpoint.');
        $url = $this->baseUrl().$this->path($template, $id);
        $this->assertLive($connection);

        if (isset($query['limit'])) {
            $query['limit'] = max(1, min(self::MAX_PAGE_SIZE, (int) $query['limit']));
        }

        $wait = null;
        for ($attempt = 1; ; $attempt++) {
            try {
                $request = Http::withToken($connection->access_token)->acceptJson()->timeout(30);
                $response = $method === 'POST'
                    ? $request->asJson()->post($url, $body ?? [])
                    : $request->get($url, $query);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) throw $e;
                $this->pause($this->backoff($attempt, null, $wait));
                continue;
            }

            if (($response->status() === 429 || $response->serverError()) && $attempt < self::MAX_ATTEMPTS) {
                $this->pause($this->backoff($attempt, $response->header('Retry-After'), $wait));
                continue;
            }

            // Slow down before being rejected when the window is nearly spent.
            $remaining = $response->header('RateLimit-Remaining');
            if ($remaining !== '' && is_numeric($remaining) && (int) $remaining <= 1) {
                $reset = $response->header('RateLimit-Reset');
                $this->pause(is_numeric($reset) ? min(self::MAX_WAIT_SECONDS, (float) $reset) : 1.0);
            }

            return $response;
        }
    }

    /**
     * Every page of a list endpoint, newest first, until $stop says enough
     * or there is no next page. Each page's data is passed to $onPage.
     *
     * @param callable(array $page): bool $onPage  return false to stop
     * @return array{ok:bool, pages:int, http_status:?int}
     */
    public function eachPage(WhatnotApiConnection $connection, string $endpoint, callable $onPage, array $query = [], int $maxPages = 50, ?string $id = null, ?string $startCursor = null, bool $allowIncomplete = false): array
    {
        $cursor = $startCursor;
        $seen = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->call($connection, $endpoint, $id, array_filter($query + ['limit' => self::MAX_PAGE_SIZE, 'cursor' => $cursor], fn ($v) => $v !== null));
            if (! $response->successful()) {
                return ['ok' => false, 'pages' => $page - 1, 'http_status' => $response->status()];
            }
            $payload = $response->json();
            if (! is_array($payload) || ! is_array($payload['data'] ?? null) || ! array_is_list($payload['data'])) {
                throw new RuntimeException('Unexpected list response: expected {data: [...], next_cursor}.');
            }
            if ($onPage($payload) === false) {
                return ['ok' => true, 'pages' => $page, 'http_status' => $response->status()];
            }
            $cursor = WhatnotApiPagination::nextCursor($payload, 'next_cursor');
            if ($cursor === null) {
                return ['ok' => true, 'pages' => $page, 'http_status' => $response->status()];
            }
            WhatnotApiPagination::assertUnseen($cursor, $seen);
        }

        if ($allowIncomplete) {
            return ['ok' => true, 'pages' => $maxPages, 'http_status' => 200, 'incomplete' => true, 'next_cursor' => $cursor];
        }
        throw new RuntimeException('Whatnot pagination limit reached before all pages were retrieved.');
    }

    /**
     * Download a completed report's CSV from the presigned report_url Whatnot
     * returned. No token is sent: the link is presigned and may be off-site.
     */
    public function downloadReport(string $url): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Report URL must use HTTPS.');
        }
        $response = Http::timeout(120)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Report download failed (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    /** Seconds to wait: Retry-After when given, else 1, 2, 4, 8… capped, plus jitter. */
    private function backoff(int $attempt, ?string $retryAfter, ?float &$previous): float
    {
        $base = is_numeric($retryAfter) ? (float) $retryAfter : 1.0;
        $wait = $previous === null ? $base : max($base, $previous * 2);
        $wait = min(self::MAX_WAIT_SECONDS, $wait);
        $previous = $wait;

        return $wait + (mt_rand(0, 250) / 1000);
    }

    /** Wait, through the injectable sleeper (tests replace it). */
    public function pause(float $seconds): void
    {
        ($this->sleeper)($seconds);
    }

    private function path(string $template, ?string $id): string
    {
        if (! str_contains($template, '{id}')) {
            return $template;
        }
        if ($id === null || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id)) {
            throw new RuntimeException('Invalid Whatnot API identifier.');
        }

        return str_replace('{id}', $id, $template);
    }

    private function assertLive(WhatnotApiConnection $connection): void
    {
        // Dev/staging must never contact Whatnot, even with accidentally copied credentials.
        if (! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false)) {
            throw new RuntimeException('Live Whatnot API requests are disabled.');
        }
        if (blank($connection->access_token)) {
            throw new RuntimeException('Whatnot API credentials are missing.');
        }
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) config('services.whatnot_seller.base_url'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || ($host !== 'whatnot.com' && ! str_ends_with($host, '.whatnot.com'))) {
            throw new RuntimeException('API host is not approved.');
        }
        if (! str_starts_with($base, 'https://')) {
            throw new RuntimeException('Whatnot API base URL must use HTTPS.');
        }

        return $base;
    }
}
