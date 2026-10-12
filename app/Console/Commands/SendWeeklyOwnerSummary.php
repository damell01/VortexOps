<?php

namespace App\Console\Commands;

use App\Notifications\WeeklyOwnerSummaryNotification;
use App\Services\Notifier;
use App\Services\WeeklyOwnerSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Monday morning: last week's numbers to the owner (Settings → Notifications
 * → "Weekly owner summary" decides who receives it and whether it emails).
 */
class SendWeeklyOwnerSummary extends Command
{
    protected $signature = 'reports:weekly-owner-summary {--week= : Any date in the week to summarise (default: last week)}';

    protected $description = 'Email the owner a summary of last week: revenue, channels, streamers, formats and open items';

    public function handle(WeeklyOwnerSummary $summary): int
    {
        $week = $this->option('week') ? Carbon::parse($this->option('week')) : now()->subWeek();
        $data = $summary->build($week->startOfWeek());

        $sent = Notifier::send('weekly_owner_summary', new WeeklyOwnerSummaryNotification($data));
        $this->info("Weekly summary for {$data['week_label']} sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
