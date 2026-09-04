<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        $source = $doOrders ? 'whatnot_orders' : ($doShipments ? 'whatnot_shipments' : 'whatnot_ledger');
        $runId = (string) Str::uuid();
        $successes = 0;
        $partials = 0;
        $failures = 0;
        $errors = [];

        foreach ($channels as $channel) {
            $this->line($channel->name . ': ' . ShowIngestionLog::sourceLabels()[$source]);

            if ($doOrders) $result = $this->refreshOrders($scraper, $channel, $hours, $limit, $runId);
            elseif ($doShipments) $result = $this->refreshShipments($engine, $channel, $limit, $runId);
            else $result = $this->refreshLedger($scraper, $channel, $ledgerDays, $runId);

            if ($result['status'] === 'success') $successes++;
            elseif ($result['status'] === 'partial') $partials++;
            else $failures++;

            if (! empty($result['error'])) $errors[] = $channel->name . ': ' . $result['error'];
        }

        $globalStatus = $failures === 0 && $partials === 0
            ? 'success'
            : (($successes > 0 || $partials > 0) ? 'partial' : 'failed');

        ShowIngestionLog::create([
            'source' => $source,
            'status' => $globalStatus,
            'raw_payload' => [
                'run_id' => $runId,
                'channels_total' => $channels->count(),
                'channels_succeeded' => $successes,
                'channels_partial' => $partials,
                'channels_failed' => $failures,
            ],
            'error_message' => $errors ? implode("\n", $errors) : null,
        ]);

        return $globalStatus === 'failed' ? self::FAILURE : self::SUCCESS;
    }

    private function refreshOrders(WhatnotScraper $scraper, WhatnotChannel $channel, int $hours, int $limit, string $runId): array
    {
        $windowStart = now()->subHours($hours);
        $windowEnd = now();

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
                if ($show->orders_count === 0) return true;
                if ((int) $show->units_sold > 0 && $show->orders_count < (int) $show->units_sold) return true;
                return $show->last_synced_at === null || $show->last_synced_at->lt($windowStart);
            })
            ->take($limit);

        $checked = 0;
        $failed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($shows as $show) {
            $checked++;
            try {
                $result = $scraper->importShowOrders($show);
                $show->update(['last_synced_at' => now()]);
                $created += (int) ($result['created'] ?? 0);
                $updated += (int) ($result['updated'] ?? 0);
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Show {$show->id}: {$e->getMessage()}";
                Log::warning('Whatnot recent order refresh failed', ['channel' => $channel->name, 'show_id' => $show->id, 'exception' => $e->getMessage()]);
            }
        }

        $status = $failed === 0 ? 'success' : ($failed < max(1, $checked) ? 'partial' : 'failed');
        $payload = ['run_id' => $runId, 'shows_checked' => $checked, 'shows_failed' => $failed, 'created' => $created, 'updated' => $updated];

        ShowIngestionLog::create([
            'whatnot_channel_id' => $channel->id,
            'source' => 'whatnot_orders',
            'status' => $status,
            'raw_payload' => $payload,
            'error_message' => $errors ? implode("\n", $errors) : null,
        ]);

        Log::info('Whatnot recent order refresh complete', ['channel' => $channel->name] + $payload);
        return ['status' => $status, 'error' => $errors ? implode('; ', $errors) : null];
    }

    private function refreshShipments(WhatnotSyncEngine $engine, WhatnotChannel $channel, int $limit, string $runId): array
    {
        try {
            $result = $engine->syncShipmentUpdatesForChannel($channel, $limit);
            $payload = ['run_id' => $runId] + $result;
            ShowIngestionLog::create([
                'whatnot_channel_id' => $channel->id,
                'source' => 'whatnot_shipments',
                'status' => 'success',
                'raw_payload' => $payload,
            ]);
            Log::info('Whatnot recent shipment refresh complete', ['channel' => $channel->name] + $result);
            return ['status' => 'success', 'error' => null];
        } catch (\Throwable $e) {
            ShowIngestionLog::create([
                'whatnot_channel_id' => $channel->id,
                'source' => 'whatnot_shipments',
                'status' => 'failed',
                'raw_payload' => ['run_id' => $runId],
                'error_message' => $e->getMessage(),
            ]);
            Log::warning('Whatnot recent shipment refresh failed', ['channel' => $channel->name, 'exception' => $e->getMessage()]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function refreshLedger(WhatnotScraper $scraper, WhatnotChannel $channel, int $days, string $runId): array
    {
        try {
            $to = now()->toDateString();
            $from = now()->subDays($days)->toDateString();
            $result = $scraper->importLedger($channel, $from, $to, false);
            $payload = ['run_id' => $runId, 'from' => $from, 'to' => $to] + $result;
            ShowIngestionLog::create([
                'whatnot_channel_id' => $channel->id,
                'source' => 'whatnot_ledger',
                'status' => 'success',
                'raw_payload' => $payload,
            ]);
            Log::info('Whatnot rolling ledger refresh complete', ['channel' => $channel->name] + $result);
            return ['status' => 'success', 'error' => null];
        } catch (\Throwable $e) {
            ShowIngestionLog::create([
                'whatnot_channel_id' => $channel->id,
                'source' => 'whatnot_ledger',
                'status' => 'failed',
                'raw_payload' => ['run_id' => $runId],
                'error_message' => $e->getMessage(),
            ]);
            Log::warning('Whatnot rolling ledger refresh failed', ['channel' => $channel->name, 'exception' => $e->getMessage()]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
