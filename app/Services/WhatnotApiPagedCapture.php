<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use RuntimeException;

/**
 * Manual read-only paged capture. Pagination parameters are configured
 * explicitly after validating the official API response format.
 */
final class WhatnotApiPagedCapture
{
    public function capture(
        WhatnotApiConnection $connection,
        string $resource,
        WhatnotApiReadOnlySync $sync,
        WhatnotSellerApiClient $client,
        string $cursorPath,
        string $cursorParameter = 'cursor',
        int $maxPages = 10
    ): array {
        if ($maxPages < 1 || $maxPages > 100) {
            throw new RuntimeException('Page limit must be between 1 and 100.');
        }
        if ($cursorParameter !== 'cursor') {
            throw new RuntimeException('Unsupported cursor query parameter.');
        }

        // Never enable a paged run without an explicit, verified API contract.
        if (! config('services.whatnot_seller.pagination_verified', false)) {
            throw new RuntimeException('Pagination is disabled until the API contract is verified.');
        }
        $seen = [];
        $cursor = null;
        $captured = 0;
        $newSnapshots = 0;
        for ($page = 1; $page <= $maxPages; $page++) {
            $query = $cursor === null ? [] : [$cursorParameter => $cursor];
            $result = $sync->capture($connection, $resource, $client, $query, $cursorPath);
            if (! $result['ok']) {
                return ['ok' => false, 'pages' => $captured, 'new_snapshots' => $newSnapshots, 'http_status' => $result['http_status']];
            }
            $captured++;
            $newSnapshots += $result['saved'] ? 1 : 0;
            $cursor = $result['next_cursor'];
            if ($cursor === null) {
                return ['ok' => true, 'pages' => $captured, 'new_snapshots' => $newSnapshots, 'complete' => true];
            }
            WhatnotApiPagination::assertUnseen($cursor, $seen);
        }
        return ['ok' => true, 'pages' => $captured, 'complete' => false];
    }
}
