<?php

namespace App\Console\Commands;

use App\Services\WhatnotApiOrderApplier;
use Illuminate\Console\Command;

/**
 * Preview, then apply, a captured Seller API orders snapshot to show orders.
 * Dry run by default; --apply writes, and only when WhatnotApiOrderApplier
 * allows it (production, live requests, verified contract, apply enabled).
 */
final class WhatnotApiApplyOrders extends Command
{
    protected $signature = 'whatnot:api-apply-orders
        {--channel= : Whatnot channel ID (required)}
        {--snapshot= : Orders snapshot ID (defaults to the newest)}
        {--apply : Write the changes instead of only previewing them}
        {--json : Print the result as JSON}';

    protected $description = 'Preview or apply a captured Whatnot API orders snapshot to show orders.';

    public function handle(WhatnotApiOrderApplier $applier): int
    {
        $channel = (int) $this->option('channel');
        if ($channel <= 0) {
            $this->error('Pass --channel=<id>.');
            return self::FAILURE;
        }
        $snapshot = $this->option('snapshot') !== null ? (int) $this->option('snapshot') : $applier->latestSnapshotId($channel);
        if ($snapshot === null) {
            $this->error('No orders snapshot captured for this channel yet.');
            return self::FAILURE;
        }

        $dryRun = ! $this->option('apply');
        try {
            $result = $applier->apply($channel, $snapshot, $dryRun);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Preview (nothing written)' : 'Applied').' — snapshot #'.$snapshot.', channel #'.$channel);
        $this->table(['Outcome', 'Orders'], collect($result['counts'])->map(fn ($n, $k) => [str_replace('_', ' ', $k), $n])->values()->all());
        foreach ($result['skipped_shows'] as $showId => $why) {
            $this->warn("Show #{$showId}: {$why}");
        }
        if ($dryRun) {
            $this->line('Run again with --apply to write these changes.');
        }

        return self::SUCCESS;
    }
}
