<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Stage raw API responses for inspection. Does NOT modify operational records.
 * Once credentials are available, this provides an auditable source for mapping.
 */
final class WhatnotApiReadOnlySync
{
    public function capture(WhatnotApiConnection $connection, string $resource, WhatnotSellerApiClient $client, array $query = [], ?string $cursorPath = null): array
    {
        if (! in_array($resource, ['shows', 'orders', 'reports'], true)) {
            throw new RuntimeException('Unsupported API resource.');
        }
        if (! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false)) {
            throw new RuntimeException('Live Whatnot capture is disabled outside approved production mode.');
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
        $inserted = DB::table('whatnot_api_snapshots')->insertOrIgnore([
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
        return ['ok' => true, 'http_status' => $response->status(), 'saved' => $inserted > 0, 'payload_sha256' => $hash, 'next_cursor' => $cursorPath === null ? null : WhatnotApiPagination::nextCursor($payload, $cursorPath)];
    }
    /**
     * Return schema metadata only, never customer data or decrypted payloads.
     * Useful for validating API response shapes before mapping operational records.
     */
    public function inspect(int $channelId, string $resource, int $limit = 5): array
    {
        if ($channelId < 1 || ! in_array($resource, ['shows', 'orders'], true) || $limit < 1 || $limit > 20) {
            throw new RuntimeException('Invalid snapshot inspection request.');
        }

        return DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)
            ->where('resource', $resource)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $json = Crypt::decryptString($row->payload);
                if (! hash_equals($row->payload_sha256, hash('sha256', $json))) {
                    throw new RuntimeException('Snapshot integrity check failed.');
                }
                $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new RuntimeException('Invalid snapshot structure.');
                }
                $data = $payload['data'] ?? null;
                return [
                    'snapshot_id' => $row->id,
                    'fetched_at' => $row->fetched_at,
                    'payload_sha256' => $row->payload_sha256,
                    'top_level_keys' => array_slice(array_keys($payload), 0, 30),
                    'data_is_list' => is_array($data) && array_is_list($data),
                    'data_count' => is_array($data) && array_is_list($data) ? count($data) : null,
                    'item_keys' => is_array($data) && array_is_list($data) && isset($data[0]) && is_array($data[0])
                        ? array_slice(array_keys($data[0]), 0, 40) : [],
                ];
            })
            ->all();
    }

    /**
     * Dry-run identity reconciliation. Only exact external show IDs are considered.
     * No API request, no writes, and no inferred timestamp/title matches.
     */
    public function previewShows(int $channelId, int $snapshotId): array
    {
        $row = DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)
            ->where('resource', 'shows')
            ->where('id', $snapshotId)
            ->first();
        if (! $row) {
            throw new RuntimeException('Show snapshot not found for this channel.');
        }
        $json = Crypt::decryptString($row->payload);
        if (! hash_equals($row->payload_sha256, hash('sha256', $json))) {
            throw new RuntimeException('Snapshot integrity check failed.');
        }
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $items = $payload['data'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Unverified show response shape; expected data list.');
        }
        $seen = [];
        $results = ['matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'invalid' => 0];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (! is_string($id) || ! preg_match('/^[a-f0-9-]{32,36}$/i', $id)) {
                $results['invalid']++;
                continue;
            }
            $id = strtolower($id);
            if (isset($seen[$id])) {
                $results['ambiguous']++;
                continue;
            }
            $seen[$id] = true;
            $count = DB::table('shows')
                ->where('whatnot_channel_id', $channelId)
                ->whereRaw('LOWER(whatnot_show_id) = ?', [$id])
                ->count();
            $results[$count === 1 ? 'matched' : ($count === 0 ? 'unmatched' : 'ambiguous')]++;
        }
        return ['snapshot_id' => $snapshotId, 'channel_id' => $channelId, 'counts' => $results,
            'writes_performed' => false, 'complete_source_verified' => false];
    }

    /**
     * Offline order-ID coverage preview. Does not infer show attribution.
     * A matching order ID alone is never sufficient for ledger approval.
     */
    public function previewOrders(int $channelId, int $snapshotId): array
    {
        $row = DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)
            ->where('resource', 'orders')
            ->where('id', $snapshotId)->first();
        if (! $row) {
            throw new RuntimeException('Order snapshot not found for this channel.');
        }
        $json = Crypt::decryptString($row->payload);
        if (! hash_equals($row->payload_sha256, hash('sha256', $json))) {
            throw new RuntimeException('Snapshot integrity check failed.');
        }
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $items = $payload['data'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Unverified order response shape; expected data list.');
        }
        $seen = [];
        $counts = ['existing_order_id' => 0, 'new_order_id' => 0, 'duplicate_source_id' => 0, 'invalid' => 0];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if ((! is_string($id) && ! is_int($id)) || trim((string) $id) === '' || strlen((string) $id) > 191) {
                $counts['invalid']++;
                continue;
            }
            $id = trim((string) $id);
            if (isset($seen[$id])) {
                $counts['duplicate_source_id']++;
                continue;
            }
            $seen[$id] = true;
            $exists = DB::table('whatnot_show_orders as o')
                ->join('shows as s', 's.id', '=', 'o.show_id')
                ->where('s.whatnot_channel_id', $channelId)
                ->where('o.whatnot_order_id', $id)->exists();
            $counts[$exists ? 'existing_order_id' : 'new_order_id']++;
        }
        return ['snapshot_id' => $snapshotId, 'channel_id' => $channelId,
            'counts' => $counts, 'show_attribution_verified' => false,
            'writes_performed' => false, 'complete_source_verified' => false];
    }

}
