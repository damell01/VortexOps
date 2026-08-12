<?php

namespace App\Console\Commands;

use App\Filament\Pages\Reports;
use App\Models\Show;
use App\Notifications\MidweekReportNotification;
use App\Services\AI\OpsDigestService;
use App\Services\NotificationRouter;
use Illuminate\Console\Command;

/**
 * Wednesday check-in: revenue and show counts for the week so far (Monday
 * through today), so a slow week is visible mid-week rather than only
 * surfacing at the Friday review reminder.
 */
class SendMidweekReport extends Command
{
    protected $signature = 'reports:midweek-report';

    protected $description = 'Email/notify admins a revenue snapshot for the week so far';

    public function handle(NotificationRouter $router, OpsDigestService $digest): int
    {
        $weekStart = now()->startOfWeek();
        $today     = now();

        // Upper bound includes a time component: show_date rows dated "today"
        // are stored with a midnight timestamp, which a bare date-string upper
        // bound would exclude via lexical comparison on SQLite.
        $shows = Show::whereBetween('show_date', [$weekStart->toDateString(), $today->copy()->endOfDay()->toDateTimeString()])
            ->whereNotIn('status', ['cancelled'])
            ->get(['gross_revenue', 'units_sold']);

        if ($shows->isEmpty()) {
            $this->info('No shows so far this week — nothing to send.');
            return self::SUCCESS;
        }

        $recipients = $router->getRecipients('midweek_report');
        $weekLabel  = $weekStart->format('M j') . ' – ' . now()->endOfWeek()->format('M j');
        $pacingPct  = Show::weekPacing()['pacing_pct'];
        $narrative  = $digest->generate();

        foreach ($recipients as $user) {
            $user->notify(new MidweekReportNotification(
                weekLabel: $weekLabel,
                showCount: $shows->count(),
                grossRevenue: (float) $shows->sum('gross_revenue'),
                unitsSold: (int) $shows->sum('units_sold'),
                reportUrl: Reports::getUrl(),
                pacingPct: $pacingPct,
                narrative: $narrative,
            ));
        }

        $this->info("Sent mid-week report to {$recipients->count()} user(s) — {$shows->count()} shows so far.");

        return self::SUCCESS;
    }
}
