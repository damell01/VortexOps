<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiLedgerImporter;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiReports;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resumable, bounded Seller API financial history. No historical order or
 * attribution deletion; unmatched scraper-covered days remain in review.
 */
final class WhatnotApiFinancialBackfill extends Command
{
    protected $signature = 'whatnot:api-financial-backfill
        {--channel= : Required Whatnot channel ID}
        {--from=2026-06-01 : Inclusive UTC start date}
        {--to= : Exclusive UTC end date (defaults to today)}
        {--max-windows=7 : Maximum one-day reports per invocation}
        {--apply : Write only when the production API apply gate allows it}
        {--reset-checkpoint : Restart at --from without deleting any records}';

    protected $description = 'Resumable one-day Seller API ledger reconciliation; dry-run by default.';

    public function handle(WhatnotApiReports $reports, WhatnotApiLedgerImporter $importer): int
    {
        $channel = (int) $this->option('channel');
        if ($channel < 1 || ! app()->environment('production')
            || ! config('services.whatnot_seller.live_requests_enabled', false)
            || ! config('services.whatnot_seller.contract_verified', false)) {
            $this->error('A channel and verified production Seller API access are required.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ! WhatnotApiOrderApplier::writesAllowed()) {
            $this->error('Production API writes are disabled.');
            return self::FAILURE;
        }
        try {
            $from = Carbon::createFromFormat('!Y-m-d', (string) $this->option('from'), 'UTC');
            $to = Carbon::createFromFormat('!Y-m-d', (string) ($this->option('to') ?: now('UTC')->toDateString()), 'UTC');
            if (! $from || ! $to || $from->format('Y-m-d') !== $this->option('from')
                || ($this->option('to') && $to->format('Y-m-d') !== $this->option('to'))
                || $from->gte($to) || $to->gt(now('UTC')->startOfDay())) {
                throw new \InvalidArgumentException('Invalid date window.');
            }
        } catch (Throwable) {
            $this->error('Use valid UTC dates: --from=YYYY-MM-DD --to=YYYY-MM-DD (exclusive, no future dates).');
            return self::FAILURE;
        }
        $connection = WhatnotApiConnection::query()->where('whatnot_channel_id', $channel)
            ->where('enabled', true)->where('sync_enabled', true)->first();
        if (! $connection) {
            $this->error('Channel API connection is not enabled for syncing.');
            return self::FAILURE;
        }
        $lock = Cache::lock('whatnot-api-sync:'.$channel, 1800);
        if (! $lock->get()) {
            $this->warn('Channel sync is already active; retry later.');
            return self::FAILURE;
        }
        try {
            $key = 'whatnot_api_financial_backfill_'.($apply ? 'apply_' : 'preview_').$channel.'_'.str_replace('-', '', $from->toDateString());
            if ($this->option('reset-checkpoint')) Setting::set($key, $from->toDateString());
            $checkpoint = (string) Setting::get($key, $from->toDateString());
            $cursor = Carbon::createFromFormat('!Y-m-d', $checkpoint, 'UTC');
            if (! $cursor || $cursor->lt($from) || $cursor->gt($to)) {
                $this->error('Checkpoint invalid. Use --reset-checkpoint to restart safely.');
                return self::FAILURE;
            }
            $limit = min(31, max(1, (int) $this->option('max-windows')));
            for ($n = 0; $n < $limit && $cursor->lt($to); $n++) {
                $end = $cursor->copy()->addDay()->min($to);
                $this->line('Channel '.$channel.' UTC '.$cursor->toDateString().' to '.$end->toDateString().' '.($apply ? 'APPLY' : 'DRY RUN'));
                $report = $reports->fetch($connection, WhatnotApiReports::financial($cursor, $end));
                if (($report['status'] ?? '') !== 'completed') {
                    $this->warn('Report '.($report['status'] ?? 'unknown').'; checkpoint unchanged. Retry later.');
                    return self::FAILURE;
                }
                $result = $importer->import($channel, $report['csv'], ! $apply, $from);
                $counts = $result['counts'];
                $this->line(json_encode(['report_id' => $report['report_id'] ?? null, 'counts' => $counts]));
                // Ambiguities and covered-day unmatched are intentionally
                // reviewable, not force-matched. Invalid rows stop progress.
                if (($counts['invalid'] ?? 0) > 0) {
                    $this->error('Invalid rows found; checkpoint unchanged for review.');
                    return self::FAILURE;
                }
                $cursor = $end;
                Setting::set($key, $cursor->toDateString());
            }
            $this->info('Checkpoint: '.$cursor->toDateString().($cursor->gte($to) ? ' (complete)' : ' (more windows remain)'));
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Backfill stopped; checkpoint retained. '.$e->getMessage());
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
