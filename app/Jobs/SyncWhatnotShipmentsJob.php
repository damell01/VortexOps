<?php

namespace App\Jobs;

use App\Models\WhatnotChannel;
use App\Services\WhatnotSyncEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs every 30 minutes to refresh weight/dimensions/carrier/shipping-status for
 * shows with orders still awaiting delivery — a tighter cadence than the hourly
 * full sync (RunWhatnotSyncJob), since shipment status is the one thing that
 * changes fast enough during active fulfillment to be worth polling separately.
 */
class SyncWhatnotShipmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    // runBatchScrape() scales up to min(3600, 300 + shows*15) — up to 50 shows
    // per channel here means up to 1050s for the scrape alone, exceeding a
    // 600s job timeout and getting this killed mid-run by the queue worker
    // (same stale-lock/orphaned-Chromium risk as RunWhatnotSyncJob). Give it
    // room for the full 3600s ceiling plus buffer.
    public int $timeout = 3900;

    public function __construct(public readonly ?int $channelId = null) {}

    public function handle(WhatnotSyncEngine $engine): void
    {
        $channels = $this->channelId
            ? WhatnotChannel::where('id', $this->channelId)->get()
            : WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

        foreach ($channels as $channel) {
            try {
                $result = $engine->syncShipmentUpdatesForChannel($channel);
                Log::info(
                    "SyncWhatnotShipmentsJob: channel \"{$channel->name}\" — " .
                    "{$result['updated']} order(s) updated across {$result['shows_checked']} show(s) checked"
                );
            } catch (\Throwable $e) {
                Log::error("SyncWhatnotShipmentsJob: channel \"{$channel->name}\" failed — {$e->getMessage()}");
            }
        }
    }
}
