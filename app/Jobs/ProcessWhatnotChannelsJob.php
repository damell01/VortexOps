<?php

namespace App\Jobs;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process every enabled Whatnot channel one at a time.
 *
 * Each channel completes its own show/analytics/order sync before we move on to
 * shipments and ledger, then the next channel. This intentionally avoids
 * parallel channel scrapes because every scraper process shares the same saved
 * Whatnot browser profile/session.
 */
class ProcessWhatnotChannelsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 14400;
    public int $uniqueFor = 18000;

    public function __construct(
        public readonly string $type = 'incremental',
        public readonly int $ledgerDays = 30,
        public readonly int $shipmentLimit = 50,
        public readonly bool $showProgress = false,
    ) {}

    public function uniqueId(): string
    {
        return 'whatnot-sequential-channel-pipeline';
    }

    private function progress(string $message): void
    {
        if (! $this->showProgress || app()->runningUnitTests()) {
            return;
        }

        fwrite(STDOUT, $message . PHP_EOL);
        fflush(STDOUT);
    }

    public function handle(WhatnotSyncEngine $engine, WhatnotScraper $scraper): void
    {
        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            Log::warning('ProcessWhatnotChannelsJob: no enabled active Whatnot channels found');
            $this->progress('No enabled active Whatnot channels found.');
            return;
        }

        $count = $channels->count();

        Log::info('ProcessWhatnotChannelsJob: starting sequential channel pipeline', [
            'channels' => $channels->pluck('whatnot_username')->values()->all(),
            'type' => $this->type,
        ]);

        $this->progress("Starting sequential Whatnot pipeline for {$count} channel(s)...");

        foreach ($channels as $position => $channel) {
            $number = $position + 1;
            $startedAt = microtime(true);

            Log::info("Whatnot pipeline [{$number}/{$count}]: starting {$channel->name}", [
                'channel_id' => $channel->id,
                'username' => $channel->whatnot_username,
            ]);

            $this->progress('');
            $this->progress("[{$number}/{$count}] {$channel->name} (@{$channel->whatnot_username})");
            $this->progress('  → Shows / analytics / orders / buyers...');

            $sync = $engine->syncChannel($channel, $this->type);

            if ($sync->status !== 'completed') {
                Log::error("Whatnot pipeline [{$number}/{$count}]: {$channel->name} show/order sync failed; skipping channel-specific follow-up steps", [
                    'sync_id' => $sync->id,
                    'errors' => $sync->errors,
                ]);

                $this->progress('  ✗ Show/order sync failed; skipping shipments and ledger for this channel.');
                continue;
            }

            $this->progress("  ✓ Shows / analytics / orders / buyers complete (sync #{$sync->id})");

            $pipeline = [
                'shows_orders_sync_id' => $sync->id,
                'shipments' => null,
                'ledger' => null,
                'errors' => [],
            ];

            $this->progress("  → Shipments (up to {$this->shipmentLimit})...");
            try {
                $pipeline['shipments'] = $engine->syncShipmentUpdatesForChannel(
                    $channel,
                    max(1, $this->shipmentLimit),
                );
                $this->progress('  ✓ Shipment refresh complete');
            } catch (\Throwable $e) {
                $pipeline['errors'][] = ['step' => 'shipments', 'message' => $e->getMessage()];
                Log::error("Whatnot pipeline: shipment refresh failed for {$channel->name}", [
                    'exception' => $e->getMessage(),
                ]);
                $this->progress('  ✗ Shipment refresh failed: ' . $e->getMessage());
            }

            $this->progress("  → Ledger ({$this->ledgerDays}-day rolling window)...");
            try {
                $to = now()->toDateString();
                $from = now()->subDays(max(1, $this->ledgerDays))->toDateString();
                $pipeline['ledger'] = $scraper->importLedger($channel, $from, $to, false);
                $this->progress('  ✓ Ledger import complete');
            } catch (\Throwable $e) {
                $pipeline['errors'][] = ['step' => 'ledger', 'message' => $e->getMessage()];
                Log::error("Whatnot pipeline: ledger import failed for {$channel->name}", [
                    'exception' => $e->getMessage(),
                ]);
                $this->progress('  ✗ Ledger import failed: ' . $e->getMessage());
            }

            $sync->update([
                'summary' => array_merge($sync->summary ?? [], [
                    'pipeline' => $pipeline,
                    'pipeline_finished_at' => now()->toIso8601String(),
                ]),
            ]);

            $elapsed = (int) round(microtime(true) - $startedAt);
            $status = empty($pipeline['errors']) ? '✓' : '⚠';
            $this->progress("  {$status} {$channel->name} finished in {$elapsed}s");

            Log::info("Whatnot pipeline [{$number}/{$count}]: finished {$channel->name}", $pipeline);
        }

        Log::info('ProcessWhatnotChannelsJob: all enabled channels attempted');
        $this->progress('');
        $this->progress('All enabled Whatnot channels attempted.');
    }
}
