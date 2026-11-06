<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dormant until explicitly activated on production. Does not use browser profiles.
 * Runs WhatnotApiSync for each enabled channel: orders, line items, show
 * analytics and the financial report. It writes only when
 * WHATNOT_SELLER_APPLY_ENABLED is on as well; otherwise it previews.
 */
final class WhatnotApiSyncTick extends Command
{
    protected $signature = 'whatnot:api-sync-tick {--channel= : Optional channel ID} {--mode=all : all, recent, analytics, or finance}';
    protected $description = 'Safely capture enabled Whatnot API resources when production API mode is active.';

    public function handle(WhatnotApiSync $sync): int
    {
        if (! app()->environment('production')
            || ! config('services.whatnot_seller.live_requests_enabled', false)
            || ! config('services.whatnot_seller.sync_scheduler_enabled', false)
            || ! config('services.whatnot_seller.contract_verified', false)) {
            $this->line('Whatnot API scheduled capture is disabled.');
            return self::SUCCESS;
        }
        $modes = [
            'all' => null,
            'recent' => ['shows', 'orders'],
            'analytics' => ['analytics', 'shipments'],
            'finance' => ['ledger', 'overview'],
        ];
        $mode = (string) $this->option('mode');
        if (! array_key_exists($mode, $modes)) {
            $this->error('Invalid mode. Use all, recent, analytics, or finance.');
            return self::FAILURE;
        }
        $query = WhatnotApiConnection::query()->where('enabled', true)->where('sync_enabled', true);
        if ($this->option('channel') !== null) {
            $query->where('whatnot_channel_id', (int) $this->option('channel'));
        }
        $failures = 0;
        foreach ($query->get() as $connection) {
            $lock = Cache::lock('whatnot-api-sync:'.$connection->whatnot_channel_id, 1800);
            if (! $lock->get()) {
                $this->warn('Channel '.$connection->whatnot_channel_id.': sync already running or lock still held; skipped.');
                continue;
            }
            try {
                // Writes only when WHATNOT_SELLER_APPLY_ENABLED is on too;
                // otherwise this is a preview that still records what it saw.
                $result = $sync->run($connection, (int) config('services.whatnot_seller.sync_days', 3),
                    dryRun: ! WhatnotApiOrderApplier::writesAllowed(), log: fn (string $line) => $this->line($line), only: $modes[$mode]);
                // Newly synced orders may supply the missing exact show evidence
                // for previously imported tips. Retry safely without touching payroll.
                if (! $result['dry_run'] && in_array($mode, ['recent', 'finance'], true)) {
                    $matcher = app(LedgerAutoMatcher::class);
                    $matched = 0;
                    $review = 0;
                    WhatnotLedgerEntry::query()
                        ->where('whatnot_channel_id', $connection->whatnot_channel_id)
                        ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                        ->where('created_date', '>=', now()->subDays(14))
                        ->orderBy('id')
                        ->chunkById(100, function ($entries) use ($matcher, &$matched, &$review) {
                            foreach ($entries as $entry) {
                                if ($matcher->match($entry) === 'matched') $matched++;
                                else $review++;
                            }
                        });
                    $this->line('Tip attribution retry: '.json_encode(['matched' => $matched, 'needs_review' => $review]));
                }
                if ($result['errors']) {
                    $failures++;
                    $this->warn('Channel '.$connection->whatnot_channel_id.': '.implode(' | ', $result['errors']));
                }
            } catch (Throwable $e) {
                $failures++;
                report($e);
                $this->warn('Sync error for channel '.$connection->whatnot_channel_id.' ('.get_class($e).')');
            } finally {
                $lock->release();
            }
        }
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
