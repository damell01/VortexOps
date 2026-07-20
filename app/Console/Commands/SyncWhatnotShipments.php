<?php

namespace App\Console\Commands;

use App\Jobs\SyncWhatnotShipmentsJob;
use App\Models\WhatnotChannel;
use App\Services\WhatnotSyncEngine;
use Illuminate\Console\Command;

class SyncWhatnotShipments extends Command
{
    protected $signature = 'whatnot:sync-shipments
                            {--channel= : Channel name or ID — omit to sync all enabled channels}
                            {--queue    : Dispatch as a background job instead of running inline}';

    protected $description = 'Refresh weight/dimensions/carrier/shipping-status for shows with open shipments';

    public function handle(WhatnotSyncEngine $engine): int
    {
        $channel = null;
        if ($channelOpt = $this->option('channel')) {
            $channel = is_numeric($channelOpt)
                ? WhatnotChannel::find($channelOpt)
                : WhatnotChannel::where('name', $channelOpt)->orWhere('whatnot_username', $channelOpt)->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");
                return self::FAILURE;
            }
        }

        if ($this->option('queue')) {
            SyncWhatnotShipmentsJob::dispatch($channel?->id);
            $label = $channel ? "channel \"{$channel->name}\"" : 'all channels';
            $this->info("Dispatched shipments sync job for {$label}.");
            return self::SUCCESS;
        }

        $channels = $channel
            ? collect([$channel])
            : WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

        if ($channels->isEmpty()) {
            $this->warn('No active channels with "Include in Import" enabled.');
            return self::FAILURE;
        }

        foreach ($channels as $ch) {
            $result = $engine->syncShipmentUpdatesForChannel($ch);
            $skippedNote = $result['skipped_shows']
                ? ", {$result['skipped_shows']} show(s) skipped (no livestream id)"
                : '';
            $this->line(
                "{$ch->name}: {$result['updated']} order(s) updated across " .
                "{$result['shows_checked']} show(s) checked{$skippedNote}"
            );
        }

        return self::SUCCESS;
    }
}
