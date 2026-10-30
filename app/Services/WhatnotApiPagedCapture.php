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

        // This workflow is intentionally not enabled until the API contract is
        // confirmed; the single-page capture remains safe to use meanwhile.
        throw new RuntimeException('Paged capture is disabled pending verified API pagination contract.');
    }
}
