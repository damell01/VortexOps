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

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly ?int $channelId = null,
        public readonly string $type = 'incremental',
    ) {}

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
