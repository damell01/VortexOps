<?php

namespace App\Console\Commands;

use App\Jobs\RunWhatnotSyncJob;
use App\Models\WhatnotChannel;
use App\Services\WhatnotSyncEngine;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class RunWhatnotSync extends Command
{
    protected $signature = 'whatnot:sync
                            {--channel=   : Channel name or ID — omit to sync all enabled channels}
                            {--type=incremental : Sync type: incremental | last_30_days | full}
                            {--queue       : Dispatch as a background job instead of running inline}';

    protected $description = 'Run the Whatnot sync engine to pull shows, orders, and buyer data';

    public function handle(WhatnotSyncEngine $engine): int
    {
        $type = $this->option('type');

        if (! in_array($type, ['incremental', 'last_30_days', 'full'])) {
            $this->error("Invalid type \"{$type}\". Must be: incremental, last_30_days, or full.");
            return self::FAILURE;
        }

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
            RunWhatnotSyncJob::dispatch($channel?->id, $type);
            $label = $channel ? "channel \"{$channel->name}\"" : 'all channels';
            $this->info("Dispatched {$type} sync job for {$label}.");
            return self::SUCCESS;
        }

        $onProgress = fn (string $line) => $this->line("      <fg=gray>" . OutputFormatter::escape($line) . "</>");

        if ($channel) {
            $this->info("Running {$type} sync for channel: {$channel->name} (@{$channel->whatnot_username})…");
            $sync = $engine->syncChannel($channel, $type, $onProgress);
            $this->printSyncResult($sync->toArray());
        } else {
            $channels = WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

            if ($channels->isEmpty()) {
                $this->warn('No active channels with "Include in Import" enabled.');
                return self::FAILURE;
            }

            $this->info("Running {$type} sync for {$channels->count()} channel(s)…");

            foreach ($channels as $ch) {
                $this->line("  → {$ch->name} (@{$ch->whatnot_username})");
                $sync = $engine->syncChannel($ch, $type, $onProgress);
                $this->printSyncResult($sync->toArray(), "    ");
            }
        }

        return self::SUCCESS;
    }

    private function printSyncResult(array $sync, string $prefix = ''): void
    {
        $status = strtoupper($sync['status']);
        $duration = $sync['duration_seconds'] ? "{$sync['duration_seconds']}s" : '—';

        $this->line("{$prefix}Status: {$status} ({$duration})");
        $this->table(
            ['Shows +', 'Shows ~', 'Orders +', 'Buyers +', 'Buyers ~', 'Errors'],
            [[
                $sync['shows_created'],
                $sync['shows_updated'],
                $sync['orders_created'],
                $sync['buyers_created'],
                $sync['buyers_updated'],
                $sync['error_count'],
            ]]
        );
    }
}
