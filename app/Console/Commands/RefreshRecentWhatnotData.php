<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshRecentWhatnotData extends Command
{
    protected $signature = 'whatnot:refresh-recent
        {--orders : Refresh orders for recent shows}
        {--shipments : Refresh unresolved shipments for recent shows}
        {--ledger : Refresh the rolling ledger window}
        {--hours=48 : Recent-show window for order refresh}
        {--limit=10 : Maximum shows per channel for each refresh}
        {--ledger-days=30 : Rolling ledger window}';

    protected $description = 'Targeted Whatnot refresh for recent orders, unresolved shipments, or ledger data';

    public function handle(WhatnotScraper $scraper, WhatnotSyncEngine $engine): int
    {
        $doOrders = (bool) $this->option('orders');
        $doShipments = (bool) $this->option('shipments');
        $doLedger = (bool) $this->option('ledger');
        if (! $doOrders && ! $doShipments && ! $doLedger) {
            $this->error('Choose at least one of --orders, --shipments, or --ledger.');
            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, min(50, (int) $this->option('limit')));
        $ledgerDays = max(1, (int) $this->option('ledger-days'));
        $channels = WhatnotChannel::where('include_in_import', true)->where('status', 'active')->orderBy('id')->get();

        foreach ($channels as $channel) {
            if ($doOrders) $this->refreshOrders($scraper, $channel, $hours, $limit);
            if ($doShipments) $this->refreshShipments($engine, $channel, $limit);
            if ($doLedger) $this->refreshLedger($scraper, $channel, $ledgerDays);
        }

        return self::SUCCESS;
    }

    private function refreshOrders(WhatnotScraper $scraper, WhatnotChannel $channel, int $hours, int $limit): void
    {
        $windowStart = now()->subHours($hours);
        $windowEnd = now();

        // Keep the routine refresh tightly scoped to shows that actually fall
        // inside the requested recent window. The upper bound is important:
        // malformed/future show dates must never be treated as recent.
        //
        // units_sold is only a prioritization hint. It is not guaranteed to
        // equal the number of order rows, so never use it as proof that an
        // order import is complete.
        $shows = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('detail_url')
            ->whereBetween('show_date', [$windowStart, $windowEnd])
            ->withCount('orders')
            ->orderByRaw('CASE WHEN last_synced_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_synced_at')
            ->orderByDesc('show_date')
            ->limit($limit * 3)
            ->get()
            ->filter(function (Show $show) use ($windowStart) {
                if ($show->orders_count === 0) {
                    return true;
                }

                if ((int) $show->units_sold > 0 && $show->orders_count < (int) $show->units_sold) {
                    return true;
                }

                // Recheck already-populated recent shows on a bounded cadence.
                // This catches late orders and pagination changes without
                // continuously hammering the same show every 30 minutes.
                return $show->last_synced_at === null || $show->last_synced_at->lt($windowStart);
            })
            ->take($limit);

        foreach ($shows as $show) {
            try {
                $result = $scraper->importShowOrders($show);
                $show->update(['last_synced_at' => now()]);
                Log::info('Whatnot recent order refresh complete', [
                    'channel' => $channel->name,
                    'show_id' => $show->id,
                    'created' => $result['created'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                    'skipped' => $result['skipped'] ?? 0,
                    'orders_in_db' => $show->orders()->count(),
                    'units_sold_hint' => (int) $show->units_sold,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Whatnot recent order refresh failed', [
                    'channel' => $channel->name,
                    'show_id' => $show->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function refreshShipments(WhatnotSyncEngine $engine, WhatnotChannel $channel, int $limit): void
    {
        try {
            $result = $engine->syncShipmentUpdatesForChannel($channel, $limit);
            Log::info('Whatnot recent shipment refresh complete', ['channel' => $channel->name] + $result);
        } catch (\Throwable $e) {
            Log::warning('Whatnot recent shipment refresh failed', [
                'channel' => $channel->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function refreshLedger(WhatnotScraper $scraper, WhatnotChannel $channel, int $days): void
    {
        try {
            $to = now()->toDateString();
            $from = now()->subDays($days)->toDateString();
            $result = $scraper->importLedger($channel, $from, $to, false);
            Log::info('Whatnot rolling ledger refresh complete', ['channel' => $channel->name] + $result);
        } catch (\Throwable $e) {
            Log::warning('Whatnot rolling ledger refresh failed', [
                'channel' => $channel->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
