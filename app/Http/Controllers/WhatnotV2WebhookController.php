<?php

namespace App\Http\Controllers;

use App\Services\WhatnotV2WebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Signed v2 delivery intake. No inventory, ledger, show or order mutations.
 * Inbound events remain in a durable encrypted inbox for validated processing.
 */
final class WhatnotV2WebhookController
{
    public function __invoke(Request $request, WhatnotV2WebhookVerifier $verifier): JsonResponse
    {
        if (! app()->environment('production')
            || ! config('services.whatnot_seller.webhooks_enabled', false)) {
            return response()->json(['message' => 'Webhook intake disabled'], 503);
        }

        $secret = (string) config('services.whatnot_seller.webhook_secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook intake unavailable'], 503);
        }

        try {
            $raw = $request->getContent();
            $event = $verifier->verify(
                $raw,
                $request->header('webhook-id'),
                $request->header('webhook-timestamp'),
                $request->header('webhook-signature'),
                $secret
            );
        } catch (Throwable) {
            return response()->json(['message' => 'Invalid delivery'], 401);
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) config('services.whatnot_seller.webhook_seller_ids', ''))));
        if ($allowed === [] || ! in_array($event['seller_id'], $allowed, true)) {
            return response()->json(['message' => 'Unrecognized seller'], 403);
        }

        $id = (string) $request->header('webhook-id');
        $digest = hash('sha256', $raw);
        $existing = DB::table('whatnot_api_webhook_events')->where('webhook_id', $id)->first();
        if ($existing) {
            if (! hash_equals($existing->payload_sha256, $digest)) {
                return response()->json(['message' => 'Conflicting event ID'], 409);
            }
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }

        DB::table('whatnot_api_webhook_events')->insertOrIgnore([
            'webhook_id' => $id,
            'seller_id' => $event['seller_id'],
            'topic' => $event['type'],
            'payload_sha256' => $digest,
            'encrypted_payload' => Crypt::encryptString($raw),
            'status' => 'pending',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['accepted' => true], 202);
    }
}
