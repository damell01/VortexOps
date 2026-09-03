<?php

use App\Jobs\ProcessWhatnotChannelsJob;
use App\Jobs\WorkerHeartbeat;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => Setting::set('scheduler_last_heartbeat', now()->toISOString()))->everyFiveMinutes()->name('scheduler-heartbeat')->withoutOverlapping();
Schedule::job(new WorkerHeartbeat)->everyFiveMinutes()->name('worker-heartbeat')->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('02:00');
Schedule::command('health:check --notify')->everyThirtyMinutes();
Schedule::command('workflow:notify-state')->everyFifteenMinutes()->name('workflow-state-notifications')->withoutOverlapping(10);

Schedule::command('payroll:sync-pay-runs')
    ->hourly()
    ->name('payroll-sync-current-week')
    ->withoutOverlapping(15);

Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])
    ->dailyAt('03:00')
    ->name('prune-ai-interactions')
    ->withoutOverlapping();

Schedule::command('activitylog:clean')
    ->weeklyOn(7, '03:30')
    ->name('clean-activity-log')
    ->withoutOverlapping();

$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);
$whatnotLog = storage_path('logs/whatnot-scheduler.log');

// Critical path: keep show discovery and analytics fresh for every channel.
// Orders/shipments/ledger are deliberately NOT part of this job, so a slow
// fulfillment page cannot prevent another channel from receiving its hourly pull.
Schedule::job(new ProcessWhatnotChannelsJob())
    ->skip($whatnotPaused)
    ->hourlyAt(5)
    ->name('whatnot-hourly-show-analytics-pull')
    ->withoutOverlapping(55);

// Recent order reconciliation. Detail enrichment is disabled by the runner for
// routine batches; only recent shows whose imported order count appears short are
// revisited. Offset from the hourly pull to reduce browser-lock contention.
Schedule::command('whatnot:refresh-recent --orders --hours=48 --limit=8')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('20,50 * * * *')
    ->name('whatnot-recent-orders-refresh')
    ->withoutOverlapping(25);

// Shipment state changes independently of show analytics. Check unresolved
// shipments on a separate cadence and stop revisiting delivered/returned orders.
Schedule::command('whatnot:refresh-recent --shipments --limit=8')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(35)
    ->name('whatnot-unresolved-shipments-refresh')
    ->withoutOverlapping(25);

// Rolling ledger reconciliation is useful but not latency-sensitive. Keeping it
// out of the hourly show pull makes the normal channel cycle predictable.
Schedule::command('whatnot:refresh-recent --ledger --ledger-days=30')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('10 */6 * * *')
    ->name('whatnot-rolling-ledger-refresh')
    ->withoutOverlapping(90);

Schedule::command('whatnot:repair-shows --apply --skip-sync --aliases-only')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('1,11,21,31,41,51 * * * *')
    ->name('whatnot-show-alias-cleanup')
    ->withoutOverlapping(10);

// Nightly reconciliation catches gaps without putting historical work on the
// hourly critical path. This intentionally uses the existing idempotent sync.
Schedule::command('whatnot:sync --type=last_30_days')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->dailyAt('00:30')
    ->name('whatnot-nightly-30-day-reconciliation')
    ->withoutOverlapping(240);

// Deep historical ledger backfill remains weekly and isolated.
Schedule::command('whatnot:import-ledger --days=1825')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('0 1 * * 0')
    ->name('whatnot-ledger-backfill-annual')
    ->withoutOverlapping(480);

Schedule::command('ai:ops operations')
    ->cron('25 */6 * * *')
    ->name('ai-ops-background-summary')
    ->withoutOverlapping(10);

Schedule::command('ai:ops cleanup')
    ->dailyAt('04:15')
    ->name('ai-ops-data-cleanup')
    ->withoutOverlapping(10);

Schedule::command('ai:ops weekly')
    ->weeklyOn(1, '07:00')
    ->name('ai-ops-weekly-management-summary')
    ->withoutOverlapping(10);

Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');

Schedule::command('inventory:snapshot-value')
    ->dailyAt('23:50')
    ->name('inventory-snapshot-value')
    ->withoutOverlapping();
