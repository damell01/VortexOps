<?php

namespace App\Services;

/** Evidence emitted only after an order is present in a filtered Whatnot response. */
class VerifiedOrderSource
{
    public static function matches(array $raw, string $orderId, string $liveId): bool
    {
        $e = $raw['_source_evidence'] ?? null;
        return is_array($e)
            && ($e['version'] ?? null) === 1
            && ($e['method'] ?? null) === 'seller_hub_filtered_response'
            && ($e['pagination_complete'] ?? null) === true
            && $orderId !== '' && $liveId !== ''
            && ($e['order_id'] ?? null) === $orderId
            && strtolower((string) ($e['live_id'] ?? '')) === strtolower($liveId);
    }
}
