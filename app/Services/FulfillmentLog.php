<?php

namespace App\Services;

use App\Models\FulfillmentEvent;
use Illuminate\Support\Facades\DB;

/**
 * Writes the fulfillment activity log. Append-only: one row per event, never
 * edited. The buyer letter and username are copied onto the row so the camera
 * lookup still reads correctly if a buyer row is later removed.
 */
final class FulfillmentLog
{
    public static function record(string $type, ?int $runId, ?int $userId, array $data = [], ?int $buyerId = null, ?int $packageId = null): void
    {
        $run = $runId ? DB::table('fulfillment_runs')->where('id', $runId)->first(['show_id', 'station_id']) : null;
        $buyer = $buyerId ? DB::table('fulfillment_run_buyers')->where('id', $buyerId)->first(['letter', 'buyer_username']) : null;

        FulfillmentEvent::query()->create([
            'run_id' => $runId,
            'show_id' => $data['show_id'] ?? $run?->show_id,
            'station_id' => $data['station_id'] ?? $run?->station_id,
            'user_id' => $userId,
            'buyer_id' => $buyerId,
            'package_id' => $packageId,
            'type' => $type,
            'buyer_letter' => $buyer?->letter,
            'buyer_username' => $buyer?->buyer_username,
            'data' => array_diff_key($data, ['show_id' => 1, 'station_id' => 1]) ?: null,
            'created_at' => now(),
        ]);
    }
}
