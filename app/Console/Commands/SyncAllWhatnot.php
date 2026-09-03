<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatnotChannelsJob;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;

class SyncAllWhatnot extends Command
{
    protected $signature = 'whatnot:sync-all
                            {--type=incremental : Sync type passed to the Whatnot channel pipeline}
                            {--ledger-days=30 : Rolling ledger window in days}
                            {--shipment-limit=50 : Maximum unresolved shipments refreshed per channel}';

    protected $description = 'Run the normal Whatnot pipeline for every enabled channel sequentially';

    public function handle(): int
    {
        $type = strtolower(trim((string) $this->option('type')));
        $allowedTypes = ['incremental', 'full'];

        if (! in_array($type, $allowedTypes, true)) {
            $this->error('Invalid --type. Allowed values: ' . implode(', ', $allowedTypes));
            return self::FAILURE;
        }

        $ledgerDays = max(1, (int) $this->option('ledger-days'));
        $shipmentLimit = max(1, (int) $this->option('shipment-limit'));

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->warn('No enabled active Whatnot channels were found.');
            return self::SUCCESS;
        }

        $this->info('Running sequential Whatnot pipeline for ' . $channels->count() . ' channel(s):');
        foreach ($channels as $channel) {
            $this->line("  - {$channel->name} (@{$channel->whatnot_username})");
        }

        $this->newLine();
        $this->line("Type: {$type}");
        $this->line("Ledger window: {$ledgerDays} day(s)");
        $this->line("Shipment limit: {$shipmentLimit} per channel");
        $this->line('Execution: synchronous/sequential (this terminal remains attached until complete)');
        $this->newLine();

        // Run the exact same job handler used by the scheduler, but synchronously.
        // This makes manual verification deterministic: there is no dependency on
        // whichever queue worker happens to be running, and each browser-backed
        // scraper call is additionally serialized by the global browser lock.
        ProcessWhatnotChannelsJob::dispatchSync(
            type: $type,
            ledgerDays: $ledgerDays,
            shipmentLimit: $shipmentLimit,
        );

        $this->newLine();
        $this->info('Sequential Whatnot pipeline finished. Check the sync records/log for per-channel results.');

        return self::SUCCESS;
    }
}
