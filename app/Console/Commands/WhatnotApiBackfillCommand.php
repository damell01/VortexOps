<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiBackfill;
use App\Services\WhatnotApiOrderApplier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Backfill channels from the Whatnot Seller API back to a date: shows,
 * analytics, orders, line items, shipments, ledger and daily overview.
 * Preview by default; --apply writes. Stopped part way? Run it again —
 * it resumes at the step it was on (--fresh to start over).
 */
final class WhatnotApiBackfillCommand extends Command
{
    protected $signature = 'whatnot:api-backfill
        {--channel=all : A channel ID, or "all" for every channel with a saved token}
        {--since=2026-01-01 : First date to backfill (Y-m-d)}
        {--only= : Comma-separated steps: shows,analytics,orders,items,shipments,ledger,overview}
        {--apply : Write changes instead of previewing}
        {--fresh : Start over instead of resuming}';

    protected $description = 'Backfill VortexOps from the Whatnot Seller API back to a date.';

    public function handle(WhatnotApiBackfill $backfill): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && ! WhatnotApiOrderApplier::writesAllowed()) {
            $this->error('Writing is off. It needs production, WHATNOT_SELLER_LIVE_REQUESTS_ENABLED, WHATNOT_SELLER_CONTRACT_VERIFIED and WHATNOT_SELLER_APPLY_ENABLED.');
            return self::FAILURE;
        }
        try {
            $since = Carbon::parse((string) $this->option('since'), config('vortex.whatnot.timezone', 'America/Chicago'))->startOfDay();
        } catch (\Throwable) {
            $this->error('--since must be a date like 2026-01-01.');
            return self::FAILURE;
        }
        $only = $this->option('only') ? array_values(array_intersect(array_map('trim', explode(',', $this->option('only'))), WhatnotApiBackfill::STEPS)) : null;

        $connections = WhatnotApiConnection::query()->whereNotNull('access_token')
            ->when($this->option('channel') !== 'all', fn ($q) => $q->where('whatnot_channel_id', (int) $this->option('channel')))
            ->get()->filter(fn ($c) => filled($c->access_token));
        if ($connections->isEmpty()) {
            $this->error('No channel with a saved API token.');
            return self::FAILURE;
        }

        $failed = false;
        foreach ($connections as $connection) {
            $this->info('Channel '.$connection->whatnot_channel_id.($apply ? '' : ' (preview — nothing written)'));
            $result = $backfill->run($connection, $since, dryRun: ! $apply, only: $only, fresh: (bool) $this->option('fresh'),
                log: fn (string $line) => $this->line($line));
            foreach ($result['errors'] as $error) {
                $failed = true;
                $this->warn($error.' — run the command again to resume.');
            }
        }
        if (! $apply) $this->line('Preview only. In a preview, shows are not created, so orders for new shows count as "show not found". Add --apply to write.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
