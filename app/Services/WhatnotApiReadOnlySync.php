<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stage raw API responses for inspection. Does NOT modify operational records.
 * Once credentials are available, this provides an auditable source for mapping.
 */
final class WhatnotApiReadOnlySync
{
    public function capture(WhatnotApiConnection $connection, string $resource, WhatnotSellerApiClient $client, array $query = []): array
    {
        if (! in_array($resource, ['shows', 'orders'], true)) {
            throw new RuntimeException('Unsupported API resource.');
        }
        $response = $client->get($connection, $resource, $query);
        if (! $response->successful()) {
            return ['ok' => false, 'http_status' => $response->status(), 'saved' => false];
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            return ['ok' => false, 'http_status' => $response->status(), 'saved' => false];
        }
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $encoded);
        $secured = \Illuminate\Support\Facades\Crypt::encryptString($encoded);
        DB::table('whatnot_api_snapshots')->insertOrIgnore([
            'whatnot_channel_id' => $connection->whatnot_channel_id,
            'resource' => $resource,
            'payload_sha256' => $hash,
            'payload' => $secured,
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        WhatnotApiSyncAudit::create([
            'whatnot_channel_id' => $connection->whatnot_channel_id,
            'resource' => $resource,
            'status' => 'captured',
            'summary' => ['payload_sha256' => $hash, 'top_level_keys' => array_slice(array_keys($payload), 0, 30)],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        return ['ok' => true, 'http_status' => $response->status(), 'saved' => true, 'payload_sha256' => $hash, 'response' => $payload];
    }
}
