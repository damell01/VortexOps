<?php

namespace App\Services;

use App\Models\Show;
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
    public function syncChannel(WhatnotChannel $channel, string $type = 'incremental'): WhatnotSync
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
            'error_count'    => 0,
        ];
        $errors = [];

        try {
            // 1 — sync shows
            $limit = match ($type) {
                'full'         => 500,
                'last_30_days' => 100,
                default        => (int) config('vortex.whatnot.limit', 50),
            };

            $showResult = $this->scraper->importShows(channel: $channel, limit: $limit);
            $counters['shows_created'] = $showResult['created'];
            $counters['shows_updated'] = $showResult['updated'];

            // 2 — sync orders for shows that have a detail_url but no orders yet
            //     (or all shows in full/last_30_days modes)
            $orderResult = $this->syncOrdersForChannel($channel, $type, $errors);
            $counters['orders_created'] += $orderResult['created'];
            $counters['orders_updated'] += $orderResult['updated'];
            $counters['error_count']    += $orderResult['errors'];

            // 3 — upsert buyer profiles from order data
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
     * Sync orders for all shows in a channel that have a detail_url.
     */
    private function syncOrdersForChannel(WhatnotChannel $channel, string $type, array &$errors): array
    {
        $query = Show::where('whatnot_channel_id', $channel->id)
            ->whereNotNull('detail_url');

        // In incremental mode, only shows without any orders yet (or synced > 7 days ago)
        if ($type === 'incremental') {
            $query->where(function ($q) {
                $q->whereDoesntHave('orders')
                  ->orWhere('last_synced_at', '<', now()->subDays(7));
            });
        } elseif ($type === 'last_30_days') {
            $query->where('show_date', '>=', now()->subDays(30));
        }

        $shows = $query->limit(50)->get();

        $created = 0;
        $updated = 0;
        $errorCount = 0;

        foreach ($shows as $show) {
            try {
                $result = $this->scraper->importShowOrders($show);
                $created += $result['created'];
                // Mark as synced
                $show->update(['last_synced_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("WhatnotSyncEngine: order sync failed for show #{$show->id} — {$e->getMessage()}");
                $errors[] = ['show_id' => $show->id, 'message' => $e->getMessage()];
                $errorCount++;
            }
        }

        return compact('created', 'updated', 'errors', 'errorCount') + ['errors' => $errorCount];
    }

    /**
     * Build or update WhatnotBuyer records from orders attached to a channel's shows.
     */
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

        // Back-fill whatnot_buyer_id on orders that don't have it yet
        DB::statement('
            UPDATE whatnot_show_orders o
            JOIN whatnot_buyers b ON b.username = o.buyer_username
            SET o.whatnot_buyer_id = b.id
            WHERE o.whatnot_buyer_id IS NULL
              AND o.buyer_username IS NOT NULL
        ');

        return compact('created', 'updated');
    }

    private function buildSummary(WhatnotChannel $channel, array $counters): array
    {
        return array_merge($counters, [
            'channel' => $channel->name,
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
