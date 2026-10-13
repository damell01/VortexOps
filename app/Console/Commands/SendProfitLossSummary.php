<?php

namespace App\Console\Commands;

use App\Notifications\ProfitLossSummaryNotification;
use App\Services\Notifier;
use App\Services\ProfitLossSummary;
use App\Support\BusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The daily (yesterday) or weekly (last week) P&L summary, in-app and by
 * email as Settings → Notifications says, listing every show.
 */
class SendProfitLossSummary extends Command
{
    protected $signature = 'reports:pl-summary {period=day : day or week} {--date= : Any date in the day/week (default: yesterday / last week)}';

    protected $description = 'Send the daily or weekly profit & loss summary listing every show';

    public function handle(ProfitLossSummary $summary): int
    {
        $period = $this->argument('period') === 'week' ? 'week' : 'day';
        $anchor = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::parse(BusinessTime::local()->toDateString())->sub($period === 'week' ? '1 week' : '1 day');

        $data = $summary->build($period, $anchor);
        $event = $period === 'week' ? 'weekly_pl_summary' : 'daily_pl_summary';
        $sent = Notifier::send($event, new ProfitLossSummaryNotification($event, $data));
        $this->info("{$data['label']}: {$data['totals']['shows']} show(s), sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
