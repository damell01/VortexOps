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

class RunWhatnotSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout;

    public function __construct(
        public readonly ?int $channelId = null,
        public readonly string $type = 'incremental',
    ) {
        // Must exceed (with buffer) WhatnotScraper::fetchShows()'s own scaled
        // timeout for the requested type, plus room for the order/buyer-sync
        // steps that follow — full mode's 500-show limit can legitimately take
        // up to ~12000s for the show-fetch step alone. A too-short job timeout
        // gets this killed mid-scrape by the queue worker, which leaves a
        // stale browser lock (see withBrowserLock()) and an orphaned Chromium
        // process behind instead of finishing normally.
        $this->timeout = match ($type) {
            'full'         => 13000,
            'last_30_days' => 3000,
            default        => 1800,
        };
    }

    public function handle(WhatnotSyncEngine $engine): void
    {
        if ($this->channelId) {
            $channel = WhatnotChannel::find($this->channelId);
            if (! $channel) {
                Log::warning("RunWhatnotSyncJob: channel #{$this->channelId} not found");
                return;
            }
            $engine->syncChannel($channel, $this->type);
        } else {
            $engine->syncAll($this->type);
        }
    }
}
