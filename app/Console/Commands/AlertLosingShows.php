<?php

namespace App\Console\Commands;

use App\Filament\Resources\ShowResource;
use App\Models\Setting;
use App\Notifications\VortexAlert;
use App\Services\Notifier;
use App\Services\ProfitLossReport;
use App\Support\BusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Tell admins once when a show turns out to have lost money — only once its
 * figures are complete (earnings, approved COGS and final pay), so the alert
 * is about a real loss rather than a number still coming in.
 */
class AlertLosingShows extends Command
{
    protected $signature = 'reports:losing-shows {--days=21 : How far back to look}';

    protected $description = 'Alert admins about shows whose completed P&L is a loss';

    public const SETTING = 'losing_show_alerts';

    public function handle(ProfitLossReport $report): int
    {
        // Set to a daily or weekly summary instead: reports:show-loss-notifications sends it.
        $schedule = app(\App\Services\NotificationRouter::class)->rule('show_lost_money')['schedule'] ?? null;
        if ($schedule && $schedule['mode'] !== 'immediate') {
            $this->info('Finalized losses go out as a summary; no per-show alerts.');

            return self::SUCCESS;
        }

        $to = Carbon::parse(BusinessTime::local()->toDateString());
        $rows = $report->rows($to->copy()->subDays((int) $this->option('days')), $to);
        $alerted = collect(json_decode((string) Setting::get(self::SETTING, '[]'), true) ?: []);
        $sent = 0;

        foreach ($rows->filter(fn ($r) => $r['profit'] < 0 && $r['incomplete'] === []) as $r) {
            if ($alerted->contains($r['id'])) {
                continue;
            }
            Notifier::send('show_lost_money', new VortexAlert(
                event: 'show_lost_money',
                title: 'A show lost money: '.$r['title'],
                body: "{$r['date']?->format('D M j')} · {$r['streamer']} · {$r['channel']}\n"
                    .'Earnings $'.number_format($r['earnings'], 2).' − COGS $'.number_format($r['cogs'], 2).' − pay $'.number_format($r['payouts'], 2)
                    .' = −$'.number_format(abs($r['profit']), 2),
                tone: 'danger',
                links: ['Open the show' => ShowResource::getUrl('view', ['record' => $r['id']])],
            ));
            $alerted->push($r['id']);
            $sent++;
        }

        Setting::set(self::SETTING, json_encode($alerted->unique()->take(-2000)->values()->all()));
        $this->info("{$sent} losing show alert(s) sent.");

        return self::SUCCESS;
    }
}
