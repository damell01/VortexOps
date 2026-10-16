<?php

namespace App\Console\Commands;

use App\Filament\Pages\ShowDataAudit;
use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Notifications\VortexAlert;
use App\Services\Notifier;
use App\Services\WhatnotReportingReconciler;
use App\Support\BusinessTime;
use App\Support\WhatnotPipelineLock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * No show that actually aired should go more than about four hours without
 * its analytics. Hourly, this finds yesterday's and today's ended shows that
 * still have none and checks exactly those, skipping any retry cooldown once
 * a show is two hours past its end. A show still missing analytics four hours
 * after it ended is reported to the owner, once.
 *
 * Shows flagged as having no Whatnot activity (a likely no-show) are left out.
 */
class CatchUpWhatnotAnalytics extends Command
{
    protected $signature = 'whatnot:analytics-catchup
        {--limit=10 : Most shows to check per channel this run}
        {--dry-run : List the overdue shows without checking or alerting}';

    protected $description = "Check analytics for yesterday's and today's ended shows that still have none; alert when one is 4+ hours overdue";

    /** Hours after a show ends before a missing analytics row is an alert. */
    public const ALERT_AFTER_HOURS = 4;

    /** Hours after a show ends before its retry cooldown is skipped. */
    public const FORCE_AFTER_HOURS = 2;

    public function handle(WhatnotReportingReconciler $reconciler): int
    {
        $due = $this->due();
        $this->line($due->count().' ended show(s) from yesterday or today without analytics.');
        if ($due->isEmpty()) {
            return self::SUCCESS;
        }
        foreach ($due as [$show, $endedAt]) {
            $this->line("  #{$show->id} {$show->title} · ended {$endedAt->diffForHumans()} · ".($show->analytics_sync_status ?? 'never checked'));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $lock = WhatnotPipelineLock::acquire('Analytics catch-up for recent shows');
        if (! $lock) {
            $this->warn('Whatnot browser busy ('.WhatnotPipelineLock::busyMessage().'); the next run picks these up.');
        } else {
            try {
                $this->check($reconciler, $due);
            } finally {
                WhatnotPipelineLock::release($lock);
            }
        }

        $this->alertOverdue($this->due());

        return self::SUCCESS;
    }

    /**
     * Yesterday's and today's shows that ended at least 30 minutes ago and
     * still need analytics, with when they ended.
     *
     * @return Collection<int, array{0: Show, 1: CarbonImmutable}>
     */
    public function due(): Collection
    {
        $now = CarbonImmutable::now(BusinessTime::zone());

        return Show::query()
            ->whereDate('show_date', '>=', $now->subDay()->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('whatnot_channel_id')
            ->readyForAnalytics()
            ->missingAnalytics()
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%'.Show::NO_ACTIVITY_FLAG.'%'))
            ->orderBy('show_date')->orderBy('id')
            ->get()
            ->map(fn (Show $show) => [$show, $this->endedAt($show)])
            ->filter(fn ($row) => $row[1]->lte($now->subMinutes(30)))
            ->values();
    }

    /** When a show ended, in the team's zone: its schedule if known, else the end of its day. */
    public function endedAt(Show $show): CarbonImmutable
    {
        $day = CarbonImmutable::parse($show->show_date->toDateString(), BusinessTime::zone());
        $end = $show->scheduleMinutes()[1];

        return $end !== null ? $day->addMinutes($end) : $day->endOfDay();
    }

    private function check(WhatnotReportingReconciler $reconciler, Collection $due): void
    {
        $limit = max(1, (int) $this->option('limit'));
        $now = CarbonImmutable::now(BusinessTime::zone());
        $channels = WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get()->keyBy('id');

        foreach ($due->groupBy(fn ($row) => $row[0]->whatnot_channel_id) as $channelId => $rows) {
            $channel = $channels->get($channelId);
            if (! $channel) {
                continue;
            }
            foreach ($rows as [$show, $endedAt]) {
                // Two hours past the end, a cooldown from an earlier miss no longer gets to wait.
                if ($show->whatnot_show_id && $endedAt->lte($now->subHours(self::FORCE_AFTER_HOURS))) {
                    Cache::forget('whatnot-analytics-retry:'.$channel->id.':'.strtolower($show->whatnot_show_id));
                }
            }
            $ids = $rows->take($limit)->map(fn ($row) => $row[0]->id)->all();
            try {
                $result = $reconciler->backfillAnalytics($channel, $now->subDays(2)->startOfDay()->toMutable(), $limit, fn ($m) => $this->line('    '.$m), $ids);
                $this->info("  {$channel->name}: {$result['updated']} updated · {$result['failed']} failed · {$result['skipped']} skipped");
            } catch (\Throwable $e) {
                report($e);
                $this->warn("  {$channel->name}: analytics check failed — {$e->getMessage()}");
            }
        }
    }

    /** Tell the owner about shows four hours past their end with no analytics, each only once. */
    private function alertOverdue(Collection $due): void
    {
        $now = CarbonImmutable::now(BusinessTime::zone());
        $overdue = $due->filter(fn ($row) => $row[1]->lte($now->subHours(self::ALERT_AFTER_HOURS)))
            ->reject(fn ($row) => Cache::has('vx-analytics-overdue:'.$row[0]->id))
            ->values();
        if ($overdue->isEmpty()) {
            return;
        }

        $lines = $overdue->map(fn ($row) => "{$row[0]->title} — ended {$row[1]->format('D g:i A')}, ".($row[0]->analytics_sync_note ?: ($row[0]->analytics_sync_status ?? 'never checked')))->all();
        Notifier::send('analytics_overdue', new VortexAlert(
            event: 'analytics_overdue',
            title: $overdue->count() === 1 ? 'A show is still missing its analytics' : $overdue->count().' shows are still missing their analytics',
            body: "These aired more than ".self::ALERT_AFTER_HOURS." hours ago and Whatnot analytics haven't come in yet:\n".implode("\n", $lines)
                ."\nIf the Whatnot sign-in lapsed, run php artisan whatnot:login. If a show never aired, mark it in No-Show Cleanup.",
            tone: 'warning',
            links: ['Open Show Data Audit' => ShowDataAudit::getUrl()],
        ));
        foreach ($overdue as [$show]) {
            Cache::put('vx-analytics-overdue:'.$show->id, true, now()->addDays(3));
        }
        $this->warn('Alerted the owner about '.$overdue->count().' overdue show(s).');
    }
}
