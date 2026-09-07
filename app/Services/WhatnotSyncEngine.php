<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotBuyer;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Models\WhatnotSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatnotSyncEngine
{
    public function __construct(private readonly WhatnotScraper $scraper) {}

    /**
     * Run a full sync for all active channels.
     * type: 'incremental' | 'last_30_days' | 'full'
     */
    public function syncAll(string $type = 'incremental'): array
    {
        $channels = WhatnotChannel::where('include_in_import', true)
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($channels as $channel) {
            $results[$channel->id] = $this->syncChannel($channel, $type);
        }
        return $results;
    }

    /**
     * Run a sync for a single channel and record it in whatnot_syncs.
     */
    public function syncChannel(WhatnotChannel $channel, string $type = 'incremental', ?callable $onProgress = null): WhatnotSync
    {
        $sync = WhatnotSync::create([
            'whatnot_channel_id' => $channel->id,
            'type'               => $type,
            'status'             => 'running',
            'started_at'         => now(),
        ]);

        Log::info("WhatnotSyncEngine: starting {$type} sync for channel \"{$channel->name}\" (sync #{$sync->id})");

        $counters = [
            'shows_created'  => 0,
            'shows_updated'  => 0,
            'orders_created' => 0,
            'orders_updated' => 0,
            'buyers_created' => 0,
            'buyers_updated' => 0,
            'items_created'  => 0,
            'items_updated'  => 0,
            'streamer_aliases_applied' => 0,
            'error_count'    => 0,
        ];
        $errors = [];

        try {
            // Incremental stays intentionally bounded. Historical modes ask the
            // scraper for a large enough window that its own pagination/stop
            // conditions, not a tiny application cap, determine completeness.
            $limit = match ($type) {
                'full'         => 10000,
                'last_30_days' => 2000,
                default        => (int) config('vortex.whatnot.limit', 50),
            };

            $showResult = $this->scraper->importShows(channel: $channel, limit: $limit, onProgress: $onProgress);
            $counters['shows_created'] = $showResult['created'];
            $counters['shows_updated'] = $showResult['updated'];
            $counters['streamer_aliases_applied'] = $this->applyKnownStreamerAliases($channel);

            if ($onProgress) {
                $onProgress("shows: {$showResult['created']} created, {$showResult['updated']} updated, {$counters['streamer_aliases_applied']} streamer alias(es) applied — scraping orders next");
            }

            $orderResult = $this->syncOrdersForChannel($channel, $type, $errors, $onProgress);
            $counters['orders_created'] += $orderResult['created'];
            $counters['orders_updated'] += $orderResult['updated'];
            $counters['error_count']    += $orderResult['errors'];

            $buyerResult = $this->syncBuyersForChannel($channel);
            $counters['buyers_created'] += $buyerResult['created'];
            $counters['buyers_updated'] += $buyerResult['updated'];

            $sync->markCompleted(array_merge($counters, [
                'errors'  => $errors ?: null,
                'summary' => $this->buildSummary($channel, $counters),
            ]));

            Log::info("WhatnotSyncEngine: sync #{$sync->id} completed", $counters);

        } catch (\Throwable $e) {
            Log::error("WhatnotSyncEngine: sync #{$sync->id} failed — {$e->getMessage()}", [
                'channel' => $channel->name,
                'trace'   => $e->getTraceAsString(),
            ]);
            $sync->markFailed($e, $errors);
        }

        return $sync->fresh();
    }

    /**
     * Apply stable Whatnot-host aliases before AI/manual review. Keep this small
     * and explicit: these are confirmed business mappings, not fuzzy guesses.
     */
    private function applyKnownStreamerAliases(WhatnotChannel $channel): int
    {
        $knownAliases = [
            'dennis_vortexcollects' => 'Dennis',
        ];

        $applied = 0;

        foreach ($knownAliases as $alias => $streamerName) {
            $streamer = Streamer::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($streamerName)])
                ->first();

            if (! $streamer) {
                Log::warning("WhatnotSyncEngine: confirmed streamer alias @{$alias} could not be applied because streamer {$streamerName} was not found");
                continue;
            }

            $shows = Show::query()
                ->where('whatnot_channel_id', $channel->id)
                ->whereDoesntHave('streamers')
                ->where(function ($q) use ($alias) {
                    $q->whereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', ['%' . strtolower($alias) . '%'])
                        ->orWhereRaw('LOWER(CAST(raw_import_payload AS CHAR)) LIKE ?', ['%' . strtolower($alias) . '%']);
                })
                ->get();

            foreach ($shows as $show) {
                $show->streamers()->syncWithoutDetaching([
                    $streamer->id => ['is_primary' => true],
                ]);
                $show->updateQuietly([
                    'ai_streamer_suggestion' => [[
                        'streamer_id' => $streamer->id,
                        'streamer_name' => $streamer->name,
                        'confidence' => 'high',
                        'reason' => "Confirmed Whatnot alias @{$alias}",
                    ]],
                ]);
                $applied++;
            }
        }

        return $applied;
    }

    private function syncOrdersForChannel(WhatnotChannel $channel, string $type, array &$errors, ?callable $onProgress = null): array
    {
        $query = Show::where('whatnot_channel_id', $channel->id)
            ->whereNotNull('detail_url')
            ->where('show_date', '<=', now()->endOfDay());

        if ($type === 'incremental') {
            $query->where(function ($q) {
                $q->whereDoesntHave('orders')
                  ->orWhere('last_synced_at', '<', now()->subDays(7));
            });
        } elseif ($type === 'last_30_days') {
            $query->where('show_date', '>=', now()->subDays(30));
        }

        $query->orderByDesc('show_date')->orderByDesc('id');
        if ($type === 'incremental') {
            $query->limit(50);
        }
        $shows = $query->get();

        $created = 0;
        $updated = 0;
        $errorCount = 0;
        $total = $shows->count();

        foreach ($shows as $i => $show) {
            if ($onProgress) {
                $onProgress("orders: [" . ($i + 1) . "/{$total}] scraping \"{$show->title}\" (#{$show->id})…");
            }
            try {
                $result = $this->scraper->importShowOrders($show);
                $created += $result['created'];
                $updated += $result['updated'] ?? 0;
                $show->update(['last_synced_at' => now()]);
                if ($onProgress) {
                    $onProgress("orders: [" . ($i + 1) . "/{$total}] \"{$show->title}\" → {$result['created']} created, " . ($result['updated'] ?? 0) . " updated");
                }
            } catch (\Throwable $e) {
                Log::warning("WhatnotSyncEngine: order sync failed for show #{$show->id} — {$e->getMessage()}");
                $errors[] = ['show_id' => $show->id, 'message' => $e->getMessage()];
                $errorCount++;
                if ($onProgress) {
                    $onProgress("orders: [" . ($i + 1) . "/{$total}] \"{$show->title}\" → ERROR: {$e->getMessage()}");
                }
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errorCount,
        ];
    }

    private function syncBuyersForChannel(WhatnotChannel $channel): array
    {
        $usernames = WhatnotShowOrder::join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
            ->where('shows.whatnot_channel_id', $channel->id)
            ->whereNotNull('whatnot_show_orders.buyer_username')
            ->distinct()
            ->pluck('whatnot_show_orders.buyer_username');

        $created = 0;
        $updated = 0;

        foreach ($usernames as $username) {
            $agg = WhatnotShowOrder::join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
                ->where('shows.whatnot_channel_id', $channel->id)
                ->where('whatnot_show_orders.buyer_username', $username)
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(whatnot_show_orders.total_price) as lifetime_spend,
                    MIN(whatnot_show_orders.show_date) as first_purchase_date,
                    MAX(whatnot_show_orders.show_date) as last_purchase_date,
                    MAX(whatnot_show_orders.buyer_display_name) as display_name
                ')
                ->first();

            $attrs = [
                'total_orders'        => (int) ($agg->total_orders ?? 0),
                'lifetime_spend'      => (float) ($agg->lifetime_spend ?? 0),
                'avg_order_value'     => $agg->total_orders > 0
                    ? round($agg->lifetime_spend / $agg->total_orders, 2)
                    : null,
                'first_purchase_date' => $agg->first_purchase_date,
                'last_purchase_date'  => $agg->last_purchase_date,
                'display_name'        => $agg->display_name ?: null,
            ];

            $buyer = WhatnotBuyer::where('username', $username)->first();

            if ($buyer) {
                $buyer->update($attrs);
                $updated++;
            } else {
                WhatnotBuyer::create(array_merge(['username' => $username], $attrs));
                $created++;
            }
        }

        if ($usernames->isNotEmpty()) {
            DB::statement('
                UPDATE whatnot_show_orders o
                JOIN whatnot_buyers b ON b.username = o.buyer_username
                SET o.whatnot_buyer_id = b.id
                WHERE o.whatnot_buyer_id IS NULL
                  AND o.buyer_username IS NOT NULL
            ');
        }

        return compact('created', 'updated');
    }

    public function syncShipmentUpdatesForChannel(WhatnotChannel $channel, int $limit = 50): array
    {
        $shows = Show::where('whatnot_channel_id', $channel->id)
            ->whereNotNull('detail_url')
            ->where('show_date', '<=', now()->endOfDay())
            ->whereHas('orders', fn ($q) => $q
                ->whereNull('shipping_status')
                ->orWhereNotIn('shipping_status', ['delivered', 'returned']))
            ->orderByDesc('show_date')
            ->limit($limit)
            ->get();

        if ($shows->isEmpty()) {
            return ['updated' => 0, 'skipped_shows' => 0, 'shows_checked' => 0];
        }

        $result = $this->scraper->refreshShipmentsForShows($shows, $channel->whatnot_username);

        return array_merge($result, ['shows_checked' => $shows->count()]);
    }

    public function syncShipmentsFromLivePage(WhatnotChannel $channel): array
    {
        $result = $this->scraper->fetchShipmentsFromLivePage($channel->whatnot_username);

        if ($result === []) {
            $known = Show::where('whatnot_channel_id', $channel->id)
                ->whereNotNull('detail_url')
                ->where('show_date', '<=', now()->endOfDay())
                ->orderByDesc('show_date')
                ->limit(50)
                ->get();

            if ($known->isNotEmpty()) {
                $refreshed = $this->scraper->refreshShipmentsForShows($known, $channel->whatnot_username);

                return [
                    'updated'      => $refreshed['updated'] ?? 0,
                    'shows_synced' => $known->count() - ($refreshed['skipped_shows'] ?? 0),
                ];
            }
        }

        $updated = 0;
        foreach ($result as $liveId => $rows) {
            $show = Show::where('whatnot_channel_id', $channel->id)
                ->where(function ($query) use ($liveId) {
                    $query->where('detail_url', 'like', "%$liveId%")
                        ->orWhereRaw("JSON_EXTRACT(raw_import_payload, '$.id') = ?", [$liveId]);
                })
                ->first();

            if (!$show) {
                Log::debug("WhatnotSyncEngine: no show found for livestream $liveId, skipping shipment sync");
                continue;
            }

            if (empty($rows)) {
                continue;
            }

            $orderRes = $this->scraper->persistShowOrders($show, $rows);
            $updated += $orderRes['updated'] ?? 0;

            $shipmentRes = $this->scraper->persistShipments($show, $rows);
            $updated += $shipmentRes['created'] ?? 0;
        }

        return ['updated' => $updated, 'shows_synced' => count($result)];
    }

    private function buildSummary(WhatnotChannel $channel, array $counters): array
    {
        return array_merge($counters, [
            'channel' => $channel->name,
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
