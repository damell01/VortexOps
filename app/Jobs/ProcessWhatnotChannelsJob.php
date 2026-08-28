<?php

namespace App\Jobs;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Bus\Queueable;
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
class ProcessWhatnotChannelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Four channels can legitimately take a while when each has shows/orders to
    // catch up. The browser layer has its own tighter per-process timeouts.
    public int $timeout = 14400;

    public function __construct(
        public readonly string $type = 'incremental',
        public readonly int $ledgerDays = 30,
        public readonly int $shipmentLimit = 50,
    ) {}

    public function handle(WhatnotSyncEngine $engine, WhatnotScraper $scraper): void
    {
        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            Log::warning('ProcessWhatnotChannelsJob: no enabled active Whatnot channels found');
            return;
        }

        Log::info('ProcessWhatnotChannelsJob: starting sequential channel pipeline', [
            'channels' => $channels->pluck('whatnot_username')->values()->all(),
            'type' => $this->type,
        ]);

        foreach ($channels as $position => $channel) {
            $number = $position + 1;

            Log::info("Whatnot pipeline [{$number}/{$channels->count()}]: starting {$channel->name}", [
                'channel_id' => $channel->id,
                'username' => $channel->whatnot_username,
            ]);

            // Step 1: shows + analytics + orders + buyers. Most importantly,
            // syncChannel passes THIS channel username into the scraper. If the
            // role switch cannot be verified, the headed wrapper returns a
            // failure instead of allowing another channel's data to be stored.
            $sync = $engine->syncChannel($channel, $this->type);

            if ($sync->status !== 'completed') {
                Log::error("Whatnot pipeline [{$number}/{$channels->count()}]: {$channel->name} show/order sync failed; skipping channel-specific follow-up steps", [
                    'sync_id' => $sync->id,
                    'errors' => $sync->errors,
                ]);

                // Do not attempt shipments/ledger on a browser session whose
                // channel switch just failed. Continue with the next channel.
                continue;
            }

            $pipeline = [
                'shows_orders_sync_id' => $sync->id,
                'shipments' => null,
                'ledger' => null,
                'errors' => [],
            ];

            // Step 2: refresh unresolved shipment metadata for this channel.
            try {
                $pipeline['shipments'] = $engine->syncShipmentUpdatesForChannel(
                    $channel,
                    max(1, $this->shipmentLimit),
                );
            } catch (\Throwable $e) {
                $pipeline['errors'][] = ['step' => 'shipments', 'message' => $e->getMessage()];
                Log::error("Whatnot pipeline: shipment refresh failed for {$channel->name}", [
                    'exception' => $e->getMessage(),
                ]);
            }

            // Step 3: keep a rolling ledger window current for this same channel.
            try {
                $to = now()->toDateString();
                $from = now()->subDays(max(1, $this->ledgerDays))->toDateString();
                $pipeline['ledger'] = $scraper->importLedger($channel, $from, $to, false);
            } catch (\Throwable $e) {
                $pipeline['errors'][] = ['step' => 'ledger', 'message' => $e->getMessage()];
                Log::error("Whatnot pipeline: ledger import failed for {$channel->name}", [
                    'exception' => $e->getMessage(),
                ]);
            }

            $sync->update([
                'summary' => array_merge($sync->summary ?? [], [
                    'pipeline' => $pipeline,
                    'pipeline_finished_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info("Whatnot pipeline [{$number}/{$channels->count()}]: finished {$channel->name}", $pipeline);
        }

        Log::info('ProcessWhatnotChannelsJob: all enabled channels attempted');
    }
}
