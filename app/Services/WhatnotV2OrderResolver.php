<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Safe exact-ID reconciliation preview for v2 order events.
 * Never invents attribution or writes to operational tables.
 */
final class WhatnotV2OrderResolver
{
    public function resolve(int $channelId, array $order): array
    {
        $id = $order['id'] ?? null;
        $showId = $order['show_id'] ?? null;
        if ((! is_string($id) && ! is_int($id)) || trim((string) $id) === '' || strlen((string) $id) > 191) {
            return ['status' => 'invalid_order_id'];
        }
        if (! is_string($showId) || ! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $showId)) {
            return ['status' => 'missing_verified_show_id', 'order_id' => (string) $id];
        }
        $shows = DB::table('shows')
            ->where('whatnot_channel_id', $channelId)
            ->whereRaw('LOWER(whatnot_show_id) = ?', [strtolower($showId)])
            ->limit(2)->pluck('id');
        if ($shows->count() !== 1) {
            return ['status' => $shows->isEmpty() ? 'show_not_imported' : 'ambiguous_show',
                'order_id' => (string) $id, 'show_uuid' => strtolower($showId)];
        }
        $conflicts = DB::table('whatnot_show_orders as o')
            ->join('shows as s', 's.id', '=', 'o.show_id')
            ->where('s.whatnot_channel_id', $channelId)
            ->where('o.whatnot_order_id', (string) $id)
            ->where('o.show_id', '<>', $shows[0])->count();
        if ($conflicts > 0) {
            return ['status' => 'conflicting_order_show', 'order_id' => (string) $id, 'writes_performed' => false];
        }
        $existing = DB::table('whatnot_show_orders')
            ->where('show_id', $shows[0])->where('whatnot_order_id', (string) $id)->count();
        return [
            'status' => $existing ? 'existing_order' : 'ready_for_validated_import',
            'order_id' => (string) $id,
            'show_id' => (int) $shows[0],
            'source_updated_at' => $order['updated_at'] ?? null,
            'writes_performed' => false,
        ];
    }
}
