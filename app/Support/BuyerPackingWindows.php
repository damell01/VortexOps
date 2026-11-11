<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * When each buyer of a show was packed on camera: start and end of packing,
 * and the station's camera, so an order can jump straight to its footage.
 * Keyed by lowercase username; one query per show, memoized per request.
 */
final class BuyerPackingWindows
{
    /** @var array<int, array<string, array{start:?Carbon, end:?Carbon, station:?string, camera:?string}>> */
    private static array $memo = [];

    public static function forShow(int $showId): array
    {
        return self::$memo[$showId] ??= DB::table('fulfillment_run_buyers as b')
            ->join('fulfillment_runs as r', 'r.id', '=', 'b.run_id')
            ->leftJoin('fulfillment_stations as s', 's.id', '=', DB::raw('COALESCE(r.station_id, r.station_occupied_id)'))
            ->where('r.show_id', $showId)->where('r.status', '!=', 'cancelled')
            ->orderBy('b.id')
            ->get(['b.buyer_username', 'b.packing_started_at', 'b.packed_at', 's.name as station', 's.camera_label'])
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim((string) $b->buyer_username)) => [
                'start' => $b->packing_started_at ? Carbon::parse($b->packing_started_at, 'UTC') : null,
                'end' => $b->packed_at ? Carbon::parse($b->packed_at, 'UTC') : null,
                'station' => $b->station, 'camera' => $b->camera_label,
            ]])->all();
    }

    /** "3:12:04 – 3:15:40 PM · Cam 2", or null when the buyer has not been packed on the board. */
    public static function label(int $showId, ?string $username): ?string
    {
        $w = self::forShow($showId)[mb_strtolower(trim((string) $username))] ?? null;
        if (! $w || (! $w['start'] && ! $w['end'])) return null;
        $tz = BusinessTime::zone();
        $start = $w['start']?->timezone($tz);
        $end = $w['end']?->timezone($tz);
        $range = $start && $end ? $start->format('M j g:i:s') . ' – ' . $end->format('g:i:s A') : ($start ? 'Started ' . $start->format('M j g:i:s A') : 'Packed ' . $end->format('M j g:i:s A'));

        return $range . (($w['camera'] ?? $w['station']) ? ' · ' . ($w['camera'] ?? $w['station']) : '');
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
