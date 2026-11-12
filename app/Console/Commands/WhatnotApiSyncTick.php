<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dormant until explicitly activated on production. Does not use browser profiles.
 * Runs WhatnotApiSync for each enabled channel: orders, line items, show
 * analytics and the financial report. It writes only when
 * WHATNOT_SELLER_APPLY_ENABLED is on as well; otherwise it previews.
 */
final class WhatnotApiSyncTick extends Command
{
    protected $signature = 'whatnot:api-sync-tick {--channel= : Optional channel ID} {--mode=all : all, fresh, catchup, recent, analytics, or finance}';
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
            // Keep the minute-level hot path short: slow analytics, shipments and
            // tip reconciliation must not block live order updates.
            'fresh' => ['shows', 'orders'],
            'catchup' => ['orders'],
            'recent' => ['shows', 'orders', 'items'],
            'analytics' => ['analytics', 'shipments'],
            'finance' => ['ledger', 'overview'],
        ];
        $mode = (string) $this->option('mode');
        if (! array_key_exists($mode, $modes)) {
            $this->error('Invalid mode. Use all, fresh, catchup, recent, analytics, or finance.');
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
            $healthKey = 'whatnot:sync-health:'.$connection->whatnot_channel_id.':'.$mode;
            $startedAt = microtime(true);
            Cache::put($healthKey.':started', now()->toIso8601String(), now()->addDays(7));
            try {
                // Writes only when WHATNOT_SELLER_APPLY_ENABLED is on too;
                // otherwise this is a preview that still records what it saw.
                $result = $sync->run($connection, ($mode === 'fresh' ? 1 : (int) config('services.whatnot_seller.sync_days', 3)),
                    dryRun: ! WhatnotApiOrderApplier::writesAllowed(), log: fn (string $line) => $this->line($line), only: $modes[$mode], analyticsLimit: null, livePriority: $mode === 'fresh', orderCatchup: $mode === 'catchup');
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
                Cache::put($healthKey.':last_result', [
                    'at' => now()->toIso8601String(),
                    'duration_seconds' => round(microtime(true) - $startedAt, 2),
                    'ok' => empty($result['errors']),
                    'errors' => array_slice($result['errors'] ?? [], 0, 5),
                    'orders' => $result['orders'] ?? null,
                ], now()->addDays(7));
                if (empty($result['errors'])) {
                    Cache::put($healthKey.':last_success', now()->toIso8601String(), now()->addDays(7));
                }
                $this->persistHealth($connection->whatnot_channel_id, $mode, empty($result['errors']), round(microtime(true) - $startedAt, 2), $result['orders'] ?? [], $result['errors'] ?? []);
                if ($result['errors']) {
                    $failures++;
                    $this->warn('Channel '.$connection->whatnot_channel_id.': '.implode(' | ', $result['errors']));
                }
            } catch (Throwable $e) {
                Cache::put($healthKey.':last_result', [
                    'at' => now()->toIso8601String(),
                    'duration_seconds' => round(microtime(true) - $startedAt, 2),
                    'ok' => false,
                    'errors' => [get_class($e).': '.$e->getMessage()],
                ], now()->addDays(7));
                $this->persistHealth($connection->whatnot_channel_id, $mode, false, round(microtime(true) - $startedAt, 2), [], [get_class($e).': '.$e->getMessage()]);
                $failures++;
                report($e);
                $this->warn('Sync error for channel '.$connection->whatnot_channel_id.' ('.get_class($e).')');
            } finally {
                $lock->release();
            }
        }
        return $failures ? self::FAILURE : self::SUCCESS;
    }
    private function persistHealth(int $channel, string $mode, bool $ok, float $duration, array $orders, array $errors): void
    {
        try {
            $previous = DB::table('whatnot_sync_health')
                ->where('whatnot_channel_id', $channel)->where('mode', $mode)->first();
            $incomplete = (bool) ($orders['incomplete'] ?? false);
            $values = [
                'last_run_at' => now(),
                'last_run_ok' => $ok,
                'duration_seconds' => $duration,
                'orders_pages' => $orders['pages'] ?? null,
                'orders_incomplete' => $orders === [] ? null : $incomplete,
                'errors' => json_encode(array_slice($errors, 0, 5)),
                'consecutive_incomplete' => $mode === 'fresh' && $ok && $incomplete
                    ? (int) ($previous->consecutive_incomplete ?? 0) + 1 : 0,
                'updated_at' => now(),
            ];
            if ($ok) $values['last_success_at'] = now();
            if ($mode === 'catchup' && $ok && $orders !== [] && ! $incomplete) $values['last_complete_at'] = now();
            if ($previous) {
                DB::table('whatnot_sync_health')->where('id', $previous->id)->update($values);
            } else {
                DB::table('whatnot_sync_health')->insert($values + [
                    'whatnot_channel_id' => $channel, 'mode' => $mode, 'created_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            // Health persistence must never stop order ingestion.
            report($e);
        }
    }

}
