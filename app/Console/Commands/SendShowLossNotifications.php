<?php

namespace App\Console\Commands;

use App\Filament\Resources\ShowResource;
use App\Models\Setting;
use App\Notifications\ShowLossSummaryNotification;
use App\Notifications\VortexAlert;
use App\Services\NotificationRouter;
use App\Services\Notifier;
use App\Services\ProfitLossReport;
use App\Services\ShowLossDigest;
use App\Support\BusinessTime;
use App\Support\NotificationSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends the "Negative Whatnot earnings" and "Finalized business loss"
 * notifications on the schedule set for each in Settings → Notifications:
 * a daily or weekly summary once its time has passed (once per period), or —
 * for negative Whatnot earnings set to Immediately — one alert per show.
 * (Finalized losses set to Immediately are sent by reports:losing-shows.)
 */
class SendShowLossNotifications extends Command
{
    protected $signature = 'reports:show-loss-notifications';

    protected $description = 'Send scheduled negative-earnings and business-loss summaries';

    public const EVENTS = ['whatnot_negative', 'show_lost_money'];

    public function handle(NotificationRouter $router, ShowLossDigest $digest, ProfitLossReport $report): int
    {
        foreach (self::EVENTS as $event) {
            $rule = $router->rule($event);
            if (! $rule['enabled'] || ! $rule['schedule']) {
                continue;
            }
            $s = $rule['schedule'];

            if ($s['mode'] === 'immediate') {
                if ($event === 'whatnot_negative') {
                    $this->alertEachShow($report, $s['business']);
                }
                continue;
            }

            $key = "notify_{$event}_last_period";
            $due = NotificationSchedule::due($s, now(), Setting::get($key));
            if (! $due) {
                continue;
            }
            $data = $digest->build($event, $due['from'], $due['to'], $s['business']);
            Setting::set($key, $due['key']);
            if ($data['count'] === 0 && $s['skip_empty']) {
                $this->info("{$event}: nothing for {$data['label']}, skipped.");
                continue;
            }
            $sent = Notifier::send($event, new ShowLossSummaryNotification($event, $data));
            $this->info("{$event}: {$data['count']} show(s) for {$data['label']}, sent to {$sent} user(s).");
        }

        return self::SUCCESS;
    }

    /** One alert per show the first time its Whatnot earnings are negative (last two days only, so switching modes never floods). */
    private function alertEachShow(ProfitLossReport $report, ?int $business): void
    {
        $to = Carbon::parse(BusinessTime::local()->toDateString());
        $alerted = collect(json_decode((string) Setting::get('whatnot_negative_alerts', '[]'), true) ?: []);
        foreach ($report->rows($to->copy()->subDays(2), $to, ['business' => $business])->filter(fn ($r) => $r['whatnot_net'] < 0) as $r) {
            if ($alerted->contains($r['id'])) {
                continue;
            }
            Notifier::send('whatnot_negative', new VortexAlert(
                event: 'whatnot_negative',
                title: 'Negative Whatnot earnings: '.$r['title'],
                body: "{$r['date']?->format('D M j')} · {$r['streamer']} · {$r['channel']}\nEstimated Whatnot earnings −$".number_format(abs($r['whatnot_net']), 2)
                    ."\nWhatnot estimates may change as analytics refresh.",
                tone: 'warning',
                links: ['Open the show' => ShowResource::getUrl('view', ['record' => $r['id']])],
            ));
            $alerted->push($r['id']);
        }
        Setting::set('whatnot_negative_alerts', json_encode($alerted->unique()->take(-2000)->values()->all()));
    }
}
