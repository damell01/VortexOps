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

// Payroll setup is intentionally frequent and idempotent. A completed show or
// corrected report can therefore reach the current Draft Pay Run without a
// payroll admin opening the screen. The command itself checks the admin setting
// before it writes anything and never changes finalized/submitted/paid weeks.
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

// One queued job owns the normal Whatnot cycle. It processes every enabled
// channel in order and does not start the next channel until the current one has
// finished shows/analytics/orders, shipment refresh, and its rolling ledger.
// This replaces separate overlapping scheduled scrapes that could all contend
// for the same persistent Whatnot browser profile.
Schedule::job(new ProcessWhatnotChannelsJob(type: 'incremental', ledgerDays: 30, shipmentLimit: 50))
    ->skip($whatnotPaused)
    ->hourlyAt(5)
    ->name('whatnot-sequential-channel-pipeline')
    ->withoutOverlapping(240);

Schedule::command('whatnot:repair-shows --apply --skip-sync --aliases-only')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('1,11,21,31,41,51 * * * *')
    ->name('whatnot-show-alias-cleanup')
    ->withoutOverlapping(10);

// Keep the deep historical ledger backfill separate from the normal rolling
// 30-day pipeline. It is intentionally infrequent and idempotent.
Schedule::command('whatnot:import-ledger --days=1825')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('0 1 * * 0')
    ->name('whatnot-ledger-backfill-annual')
    ->withoutOverlapping(480);

Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');

Schedule::command('inventory:snapshot-value')
    ->dailyAt('23:50')
    ->name('inventory-snapshot-value')
    ->withoutOverlapping();
