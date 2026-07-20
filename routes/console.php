<?php

use App\Jobs\WorkerHeartbeat;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => Setting::set('scheduler_last_heartbeat', now()->toISOString()))->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();
// Enqueue a heartbeat job every minute; a live worker stamps worker_last_heartbeat
// when it runs, so the System Health page can tell whether the queue is being drained.
Schedule::job(new WorkerHeartbeat)->everyMinute()->name('worker-heartbeat')->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('02:00');
Schedule::command('health:check --notify')->everyFifteenMinutes();

// Keep append-only tables from growing forever.
// AI telemetry: prune interactions older than 30 days (see AiInteraction::prunable()).
Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])
    ->dailyAt('03:00')
    ->name('prune-ai-interactions')
    ->withoutOverlapping();
// Spatie audit log: clean records past the configured retention window.
Schedule::command('activitylog:clean')
    ->weeklyOn(7, '03:30')
    ->name('clean-activity-log')
    ->withoutOverlapping();
// Frequent "catch the stream that just ended" import: the analytics walk starts
// at the newest show, so a small limit grabs just-ended shows quickly (dedup
// updates existing ones). Imports each show's orders in the same browser session.
// withoutOverlapping keeps runs from stacking / colliding on the shared browser
// profile — critical since every whatnot:* command drives the same Chromium profile.
Schedule::command('whatnot:import --limit=15')
    ->cron('*/15 * * * *')
    ->name('whatnot-import-recent')
    ->withoutOverlapping(30)
    // Track import health: stamp a success timestamp so the dashboard and the
    // health check can tell when the scrape pipeline last worked, and record
    // failures so a broken scraper doesn't fail silently.
    ->onSuccess(fn () => Setting::set('whatnot_last_import_success_at', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_last_import_failure_at', now()->toISOString()));

// Order backfill for older shows (beyond the recent window above) that have a
// detail_url but no orders yet. Once an hour at :22 — a quiet minute between the
// :15 and :30 imports — so it can't fight the import for the browser profile.
Schedule::command('whatnot:import-orders --new-only')
    ->cron('22 * * * *')
    ->name('whatnot-import-orders-backfill')
    ->withoutOverlapping(30);

// Daily Whatnot ledger pull — grabs the last 8 days so late-completing entries
// are caught (dedup keeps re-scraped rows from duplicating). Runs at :52, clear
// of the imports above, so it never contends for the browser profile.
Schedule::command('whatnot:import-ledger --days=8')
    ->cron('52 4 * * *')
    ->name('whatnot-ledger-daily')
    ->withoutOverlapping(30);

Schedule::command('whatnot:sync')->hourly()->name('whatnot-sync-hourly')->withoutOverlapping(10);

// Shipment-detail refresh (weight/dims/carrier/status) for shows with orders still
// awaiting delivery — a tighter cadence than the hourly full sync above, since
// fulfillment status is the one thing worth polling fast. :07/:37 keeps it clear
// of the :00/:15/:22/:30/:45/:52 Whatnot cron slots elsewhere in this file.
Schedule::command('whatnot:sync-shipments')
    ->cron('7,37 * * * *')
    ->name('whatnot-sync-shipments')
    ->withoutOverlapping(20);

// Mid-week revenue snapshot (Wednesday) and a Friday nudge for anything still
// sitting in Pending Review/Approval — so a slow week or a review backlog
// surfaces before it becomes a scramble at week's end.
Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');
