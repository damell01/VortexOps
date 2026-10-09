<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Support\WhatnotBrowserLock;
use App\Support\WhatnotPipelineLock;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Formatter\OutputFormatter;

class BackfillWhatnotNightly extends Command
{
    protected $signature = 'whatnot:backfill-nightly
        {--since=2026-07-01 : Historical reporting start}
        {--timezone=America/Chicago : Local timezone for the overnight window}
        {--start=01:00 : Window start in local time}
        {--end=05:00 : Stop browser work by this local time}
        {--dry-run : Show coverage and schedule without opening a browser}';

    protected $description = 'Discover historical shows and backfill missing analytics overnight, without orders or shipments';

    public function handle(WhatnotReportingReconciler $reconciler): int
    {
        try {
            $since = Carbon::createFromFormat('!Y-m-d', (string) $this->option('since'));
            if ($since->format('Y-m-d') !== $this->option('since') || $since->isFuture()) {
                throw new \InvalidArgumentException('Use a past YYYY-MM-DD start date.');
            }
            $timezone = (string) $this->option('timezone');
            new \DateTimeZone($timezone);
            foreach (['start', 'end'] as $option) {
                if (! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', (string) $this->option($option))) {
                    throw new \InvalidArgumentException('Window times must use HH:MM.');
                }
            }
            $localNow = now($timezone);
            $start = $localNow->copy()->setTimeFromTimeString((string) $this->option('start'));
            $end = $localNow->copy()->setTimeFromTimeString((string) $this->option('end'));
            if ($end->lte($start) || $start->diffInSeconds($end) > 14400) {
                throw new \InvalidArgumentException('Use a same-day window of no more than four hours.');
            }
        } catch (\Throwable $e) {
            $this->error('Invalid backfill options: '.$e->getMessage());
            return self::FAILURE;
        }

        $channels = WhatnotChannel::where('status', 'active')->where('include_in_import', true)->orderBy('id')->get()
            ->sortBy(fn ($channel) => (int) Cache::get('whatnot-nightly-last-attempt:'.$channel->id, 0))->values();
        $this->info('WHATNOT OVERNIGHT HISTORY: shows + analytics; orders OFF; shipments OFF');
        $this->line('Since '.$since->toDateString().' · '.$this->option('start').'–'.$this->option('end').' '.$timezone);
        foreach ($channels as $channel) {
            $count = $this->due($channel->id, $since)->count();
            $this->line($channel->name.": {$count} database show(s) due for analytics");
        }
        if ($this->option('dry-run')) {
            $this->line('Dry run: no browser, locks, or data changes.');
            return self::SUCCESS;
        }
        if ($localNow->lt($start) || $localNow->gte($end)) {
            $this->line('Outside the overnight window; no browser work started.');
            return self::SUCCESS;
        }
        if (! config('vortex.whatnot.schedule_enabled', true)) {
            $this->line('Whatnot scheduling is paused.');
            return self::SUCCESS;
        }
        WhatnotPipelineLock::recoverIfStale();
        $lock = WhatnotPipelineLock::acquire('Overnight historical shows and analytics from '.$since->toDateString());
        if (! $lock) {
            $this->line('Overnight backfill skipped — '.WhatnotPipelineLock::busyMessage());
            return self::SUCCESS;
        }
        $oldDeadline = config('vortex.whatnot.runtime_deadline');
        $oldFailFast = config('vortex.whatnot.browser_lock_fail_fast');
        $deadline = microtime(true) + max(0, $localNow->diffInSeconds($end));
        $remaining = fn () => $deadline - microtime(true);
        $progress = fn (string $line) => $this->line('  '.OutputFormatter::escape($line));
        $pending = [];
        $failures = 0;

        try {
            WhatnotBrowserLock::recoverIfStale();
            if (WhatnotBrowserLock::holder() !== null) {
                $this->line('Overnight backfill skipped — the shared browser is busy.');
                return self::SUCCESS;
            }
            config(['vortex.whatnot.runtime_deadline' => $deadline, 'vortex.whatnot.browser_lock_fail_fast' => true]);

            // Discover every channel before spending the rest of the night on analytics.
            foreach ($channels as $channel) {
                if ($remaining() < 60) break;
                Cache::put('whatnot-nightly-last-attempt:'.$channel->id, now()->timestamp, now()->addDays(30));
                $this->info('Discovering historical shows: '.$channel->name);
                try {
                    $result = $reconciler->discoverShows($channel, $progress, $since);
                    $this->record($channel->id, 'success', ['phase' => 'discovery', 'since' => $since->toDateString(), 'result' => $result]);
                } catch (\Throwable $e) {
                    $failures++;
                    $this->warn('Discovery failed: '.$e->getMessage());
                    $this->record($channel->id, 'failed', ['phase' => 'discovery', 'error' => $e->getMessage()]);
                }
                // Snapshot IDs once: each candidate gets at most one attempt per night.
                // Historical rows are included; completed analytics are left alone.
                $pending[$channel->id] = $this->due($channel->id, $since)->orderByDesc('show_date')->orderByDesc('id')->pluck('id')->all();
            }

            // Round-robin ten-show batches keep one large channel from starving the others.
            do {
                $worked = false;
                foreach ($channels as $channel) {
                    if ($remaining() < 60) break 2;
                    if (empty($pending[$channel->id])) continue;
                    $ids = array_splice($pending[$channel->id], 0, 10);
                    $worked = true;
                    $this->info('Analytics batch: '.$channel->name.' · '.count($ids).' show(s)');
                    try {
                        $result = $reconciler->backfillAnalytics($channel, $since, 10, $progress, $ids);
                        $this->record($channel->id, $result['failed'] > 0 ? 'failed' : 'success', [
                            'phase' => 'analytics', 'since' => $since->toDateString(), 'show_ids' => $ids, 'result' => $result,
                        ]);
                        $failures += (int) $result['failed'];
                    } catch (\Throwable $e) {
                        $failures++;
                        $this->warn('Analytics batch failed; remaining batches continue: '.$e->getMessage());
                        $this->record($channel->id, 'failed', ['phase' => 'analytics', 'show_ids' => $ids, 'error' => $e->getMessage()]);
                    }
                }
            } while ($worked);
        } finally {
            config(['vortex.whatnot.runtime_deadline' => $oldDeadline, 'vortex.whatnot.browser_lock_fail_fast' => $oldFailFast]);
            WhatnotPipelineLock::release($lock);
        }

        foreach ($channels as $channel) {
            $this->line($channel->name.': '.$this->due($channel->id, $since)->count().' still due for analytics; unfinished work resumes next night.');
        }
        $this->info('Overnight backfill finished. Failed or unpublished metrics remain due; check the batch results above.');
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function due(int $channelId, Carbon $since): \Illuminate\Database\Eloquent\Builder
    {
        return Show::where('whatnot_channel_id', $channelId)->whereDate('show_date', '>=', $since->toDateString())
            ->readyForAnalytics()->missingAnalytics();
    }

    private function record(int $channelId, string $status, array $payload): void
    {
        ShowIngestionLog::create([
            'whatnot_channel_id' => $channelId, 'source' => 'whatnot_nightly_backfill',
            'status' => $status, 'raw_payload' => $payload,
        ]);
    }
}
