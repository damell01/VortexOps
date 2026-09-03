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
        // Only revisit recent shows. Prefer shows that have no orders or where
        // analytics says more units were sold than we currently have imported.
        $shows = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('detail_url')
            ->where('show_date', '>=', now()->subHours($hours)->startOfDay())
            ->withCount('orders')
            ->orderByDesc('show_date')
            ->limit($limit * 3)
            ->get()
            ->filter(fn (Show $show) => $show->orders_count === 0 || ((int) $show->units_sold > 0 && $show->orders_count < (int) $show->units_sold))
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
