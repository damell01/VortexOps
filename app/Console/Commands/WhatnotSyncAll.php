<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatnotSyncAll extends Command
{
    protected $signature = 'whatnot:sync-all
                            {--limit=500 : Max shows per channel}
                            {--skip-orders : Skip order import}
                            {--skip-shipments : Skip shipment sync}
                            {--skip-ledger : Skip ledger import}
                            {--skip-shows : Skip show import}';

    protected $description = 'Run full Whatnot sync pipeline: shows → orders → shipments → ledger (all at once)';

    public function handle(): int
    {
        $this->info('🚀 Starting full Whatnot sync pipeline...');
        $this->line('');

        // Auto-cleanup any stale locks before we run
        $this->cleanupStaleLock();

        $startTime = now();
        $limit = $this->option('limit');

        // Step 1: Import shows
        if (! $this->option('skip-shows')) {
            $this->info('📺 Step 1/4: Importing shows from all channels...');
            $result = $this->call('whatnot:import', ['--limit' => $limit, '--no-orders' => true]);
            if ($result !== self::SUCCESS) {
                $this->error('Failed to import shows');
                return self::FAILURE;
            }
        }

        $this->line('');

        // Step 2: Import orders
        if (! $this->option('skip-orders')) {
            $this->info('📦 Step 2/4: Importing orders for all shows...');
            $result = $this->call('whatnot:import-orders', ['--recent' => true]);
            if ($result !== self::SUCCESS) {
                $this->error('Failed to import orders (non-fatal, continuing...)');
            }
        }

        $this->line('');

        // Step 3: Sync shipment info
        if (! $this->option('skip-shipments')) {
            $this->info('🚚 Step 3/4: Syncing shipment details (weight, carrier, status)...');
            $result = $this->call('whatnot:sync-shipments');
            if ($result !== self::SUCCESS) {
                $this->error('Failed to sync shipments (non-fatal, continuing...)');
            }
        }

        $this->line('');

        // Step 4: Import financial ledger
        if (! $this->option('skip-ledger')) {
            $this->info('💰 Step 4/4: Importing financial ledger...');
            $result = $this->call('whatnot:import-ledger');
            if ($result !== self::SUCCESS) {
                $this->error('Failed to import ledger (non-fatal, continuing...)');
            }
        }

        $this->line('');
        $this->info('✅ Full sync pipeline complete!');
        $this->line('Duration: ' . $startTime->diffForHumans(now(), ['parts' => 2, 'absolute' => true]));

        return self::SUCCESS;
    }

    private function cleanupStaleLock(): void
    {
        $holder = \App\Support\WhatnotBrowserLock::holder();

        // Only clear a lock this machine's own dead process left behind. A
        // holder on another host is not ours to judge, and a live one is doing
        // real work.
        if ($holder && $holder['host'] === gethostname() && ! $holder['alive']) {
            \App\Support\WhatnotBrowserLock::forceRelease();
            $this->info("🔓 Cleaned up stale lock (PID {$holder['pid']} not alive)");
        }
    }

    private function pidIsAlive(int $pid): bool
    {
        return \App\Support\WhatnotBrowserLock::pidIsAlive($pid);
    }
}
