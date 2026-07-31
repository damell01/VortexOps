<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use App\Services\WhatnotSyncEngine;
use Illuminate\Console\Command;

class SyncWhatnotShipmentsLive extends Command
{
    protected $signature = 'whatnot:sync-shipments-live
                            {--channel= : Channel name or ID — omit to sync all enabled channels}';

    protected $description = 'Discover shows from /dashboard/lives and scrape shipments (for channels without stored livestream IDs)';

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

        $channels = $channel
            ? collect([$channel])
            : WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

        if ($channels->isEmpty()) {
            $this->warn('No active channels with "Include in Import" enabled.');
            return self::FAILURE;
        }

        foreach ($channels as $ch) {
            $result = $engine->syncShipmentsFromLivePage($ch);
            $this->line(
                "{$ch->name}: {$result['updated']} order(s) updated across " .
                "{$result['shows_synced']} show(s) synced"
            );
        }

        return self::SUCCESS;
    }
}
