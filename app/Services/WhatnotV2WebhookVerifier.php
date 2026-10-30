<?php

namespace App\Services;

use RuntimeException;

/**
 * Verify current Whatnot REST v2 Standard Webhooks deliveries before ingestion.
 * This class does not interact with scraper sessions or operational records.
 */
final class WhatnotV2WebhookVerifier
{
    public function verify(string $body, ?string $eventId, ?string $timestamp, ?string $signatures, string $secret): array
    {
        if ($body === '' || strlen($body) > 1048576 || ! is_string($eventId) || ! preg_match('/^[A-Za-z0-9_-]{1,255}$/', $eventId)
            || ! is_string($timestamp) || ! ctype_digit($timestamp)
            || ! is_string($signatures) || $signatures === '' || $secret === '') {
            throw new RuntimeException('Invalid webhook headers or payload.');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Webhook delivery timestamp is outside the permitted window.');
        }
        $key = str_starts_with($secret, 'wn_wh_') ? substr($secret, 6) : $secret;
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) < 16) {
            throw new RuntimeException('Invalid webhook signing key configuration.');
        }
        $expected = base64_encode(hash_hmac('sha256', $eventId.'.'.$timestamp.'.'.$body, $decoded, true));
        $valid = false;
        foreach (preg_split('/\\s+/', trim($signatures)) as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                $valid = true;
            }
        }
        if (! $valid) {
            throw new RuntimeException('Webhook signature verification failed.');
        }
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ($payload['version'] ?? null) !== 'v2'
            || ! is_string($payload['type'] ?? null)
            || ! is_string($payload['seller_id'] ?? null)
            || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Invalid v2 webhook envelope.');
        }
        return $payload;
    }
}
