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

Schedule::job(new ProcessWhatnotChannelsJob())
    ->skip($whatnotPaused)
    ->hourlyAt(5)
    ->name('whatnot-hourly-show-analytics-pull')
    ->withoutOverlapping(55);

// Fill missing Gross / Estimated Net one UUID at a time for recently completed shows.
// This is intentionally separate from the fast hourly show pull so one stubborn
// historical show cannot hold up current show discovery.
Schedule::command('whatnot:backfill-missing-analytics --days=14 --limit=8 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(15)
    ->name('whatnot-missing-analytics-backfill')
    ->withoutOverlapping(45);

// Order-count polling is intentionally not scheduled. Operational views use
// Whatnot analytics plus shipment records; the legacy order table is retained
// for historical/reconciliation use without spending browser time refreshing it.

Schedule::command('whatnot:refresh-recent --shipments --limit=8 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(35)
    ->name('whatnot-unresolved-shipments-refresh')
    ->withoutOverlapping(25);

Schedule::command('whatnot:refresh-recent --ledger --ledger-days=30 --skip-if-busy')
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

// Long maintenance jobs use the same coordinator too. They record their own
// ingestion outcome inside the wrapper; a busy schedule is a clean skip rather
// than a false red failure.
Schedule::command('whatnot:run-maintenance nightly --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->dailyAt('00:30')
    ->name('whatnot-nightly-30-day-reconciliation')
    ->withoutOverlapping(240);

Schedule::command('whatnot:run-maintenance deep --skip-if-busy')
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
