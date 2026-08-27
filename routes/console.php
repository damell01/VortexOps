<?php

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

Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])
    ->dailyAt('03:00')
    ->name('prune-ai-interactions')
    ->withoutOverlapping();

Schedule::command('activitylog:clean')
    ->weeklyOn(7, '03:30')
    ->name('clean-activity-log')
    ->withoutOverlapping();

$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);

/**
 * Where a scheduled Whatnot run's output goes.
 *
 * Laravel discards a scheduled command's output unless it is told not to, and
 * the scheduler here is a bare `while true; do schedule:run; sleep 60; done`
 * with nowhere for stdout to land. So every one of these ran blind: a job could
 * fail every ten minutes for a day — printing the exact reason each time — and
 * the only visible symptom was data quietly not arriving.
 *
 * Appended rather than overwritten, because the interesting question is nearly
 * always "when did this start failing", which needs the runs either side of it.
 */
$whatnotLog = storage_path('logs/whatnot-scheduler.log');

// Every fifteen minutes, not ten. Every browser job queues behind one Chromium
// profile, and the hour was oversubscribed: six of these plus two refresh-recent
// plus a twenty-minute import-orders came to more browser time than an hour
// holds, so runs sat waiting out their twenty-minute lock timeout and were
// dropped. Five shows every fifteen minutes still clears about 480 a day.
Schedule::command('whatnot:sync-show-index --limit=200 --enrich=5')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('0,15,30,45 * * * *')
    ->name('whatnot-show-index')
    ->withoutOverlapping(20)
    ->onSuccess(fn () => Setting::set('whatnot_last_import_success_at', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_last_import_failure_at', now()->toISOString()));

Schedule::command('whatnot:repair-shows --apply --skip-sync --aliases-only')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('1,11,21,31,41,51 * * * *')
    ->name('whatnot-show-alias-cleanup')
    ->withoutOverlapping(10);

// whatnot:refresh-recent is deliberately not scheduled any more. It and
// sync-show-index now select on the same definition of "missing" and enrich the
// same way through the same script, so running both only doubled the contention
// for the browser without fetching anything the other would not have. The
// command stays for driving a refresh by hand.

// Bounded on purpose. Unbounded, this walked every show without orders and held
// the browser lock for hours. Fifteen took about twenty minutes — a third of
// every hour, during which nothing else could scrape — so eight, at roughly ten
// minutes, leaves room for the other jobs. It still clears a couple of hundred
// shows a day.
Schedule::command('whatnot:import-orders --new-only --limit=8')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('22 * * * *')
    ->name('whatnot-import-orders-backfill')
    ->withoutOverlapping(240);

Schedule::command('whatnot:import-ledger --days=30')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->cron('52 4 * * *')
    ->name('whatnot-ledger-daily')
    ->withoutOverlapping(240);

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
