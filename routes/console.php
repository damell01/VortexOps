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
// Enqueue a heartbeat job every 5 minutes; a live worker stamps worker_last_heartbeat
// when it runs, so the System Health page can tell whether the queue is being drained.
Schedule::job(new WorkerHeartbeat)->everyFiveMinutes()->name('worker-heartbeat')->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('02:00');
Schedule::command('health:check --notify')->everyThirtyMinutes();

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
// Every whatnot:* job below drives the same Chromium profile behind the same
// lock, so they pause as a group rather than one at a time: while any one of
// them is running, a manual run simply queues behind it, which makes the
// scraper the one thing you cannot debug by hand exactly when you need to.
// Stopping the scheduler outright would also stop backups and health checks.
//
// Evaluated per run, so flipping WHATNOT_SCHEDULE_ENABLED takes effect on the
// next tick without restarting anything.
$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);

// Frequent "catch the stream that just ended" import: the analytics walk starts
// at the newest show, so a larger limit grabs more shows for comprehensive updates.
// Imports each show's orders in the same browser session.
//
// withoutOverlapping keeps runs from stacking / colliding on the shared browser
// profile — critical since every whatnot:* command drives the same Chromium
// profile. Its argument is the mutex's expiry in minutes, and it MUST exceed the
// job's real worst-case runtime: once it lapses the next tick starts a duplicate
// on top of a run that never finished. At 30 minutes against runs that queue for
// the browser lock, that is exactly what happened — an import from 14:00, an
// import-orders from 14:22 and a second import from 16:00 were all alive at once,
// each waiting on a lock the others held.
Schedule::command('whatnot:import --limit=200')
    ->skip($whatnotPaused)
    ->cron('*/30 * * * *')
    ->name('whatnot-import-recent')
    ->withoutOverlapping(240)
    // Track import health: stamp a success timestamp so the dashboard and the
    // health check can tell when the scrape pipeline last worked, and record
    // failures so a broken scraper doesn't fail silently.
    ->onSuccess(fn () => Setting::set('whatnot_last_import_success_at', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_last_import_failure_at', now()->toISOString()));

// Order backfill for older shows (beyond the recent window above) that have a
// detail_url but no orders yet. Once an hour at :22 — a quiet minute between the
// :15 and :30 imports — so it can't fight the import for the browser profile.
Schedule::command('whatnot:import-orders --new-only')
    ->skip($whatnotPaused)
    ->cron('22 * * * *')
    ->name('whatnot-import-orders-backfill')
    ->withoutOverlapping(240);

// Daily Whatnot ledger pull — grabs the last 30 days for comprehensive financial data
// (dedup keeps re-scraped rows from duplicating). Runs at :52, clear of the imports above,
// so it never contends for the browser profile.
Schedule::command('whatnot:import-ledger --days=30')
    ->skip($whatnotPaused)
    ->cron('52 4 * * *')
    ->name('whatnot-ledger-daily')
    ->withoutOverlapping(240);

// Weekly historical backfill: Deep pull of past 365 days for ledger + comprehensive show/order data
// Runs Sunday at 01:00 AM to avoid peak hours. This ensures you have a full year of financial
// and operational history. Use --limit=0 for unlimited shows to backfill complete historical data.
Schedule::command('whatnot:import-ledger --days=1825')
    ->skip($whatnotPaused)
    ->cron('0 1 * * 0')
    ->name('whatnot-ledger-backfill-annual')
    ->withoutOverlapping(480);

// Full comprehensive sync: shows + orders + shipments + ledger in one go
// Run every 2 hours to pull everything at once (high limit to capture all recent activity)
Schedule::command('whatnot:sync-all --limit=0')
    ->skip($whatnotPaused)
    ->cron('0 */2 * * *')
    ->name('whatnot-sync-all')
    ->withoutOverlapping(240)
    ->onSuccess(fn () => Setting::set('whatnot_sync_all_last_success', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_sync_all_last_failure', now()->toISOString()));

// Alternative: Keep individual commands for more granular control
// Schedule::command('whatnot:sync')->hourly()->name('whatnot-sync-hourly')->withoutOverlapping(10);

// Shipment-detail refresh (weight/dims/carrier/status) for shows with orders still
// awaiting delivery — a tighter cadence than the hourly full sync above, since
// fulfillment status is the one thing worth polling fast. :07/:37 keeps it clear
// of the :00/:15/:22/:30/:45/:52 Whatnot cron slots elsewhere in this file.
Schedule::command('whatnot:sync-shipments')
    ->skip($whatnotPaused)
    ->cron('37 * * * *')
    ->name('whatnot-sync-shipments')
    ->withoutOverlapping(120);

// Mid-week revenue snapshot (Wednesday) and a Friday nudge for anything still
// sitting in Pending Review/Approval — so a slow week or a review backlog
// surfaces before it becomes a scramble at week's end.
Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');

// End-of-day inventory valuation snapshot, per channel + combined — the source
// data for monthly average-value-vs-sales reporting (a live query only ever
// shows the current moment, not a trend).
Schedule::command('inventory:snapshot-value')
    ->dailyAt('23:50')
    ->name('inventory-snapshot-value')
    ->withoutOverlapping();
