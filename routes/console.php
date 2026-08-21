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

Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])
    ->dailyAt('03:00')
    ->name('prune-ai-interactions')
    ->withoutOverlapping();

Schedule::command('activitylog:clean')
    ->weeklyOn(7, '03:30')
    ->name('clean-activity-log')
    ->withoutOverlapping();

// Every Whatnot browser task shares one persistent Chromium profile. Keep them
// serialized and allow all of them to be paused together while debugging.
$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);

// Primary Whatnot heartbeat. In one authenticated Seller Hub browser session it:
//   1) refreshes Currently Live + Upcoming shows,
//   2) infinite-scrolls the Past tab and upserts completed shows by UUID,
//   3) enriches a small backlog batch with the exact Past-row Analytics and
//      View Shipments links, persisting analytics on shows and shipment rows in
//      the existing shipments table.
// Keeping enrichment bounded prevents a 10-minute heartbeat from hammering Whatnot;
// successive runs naturally work through older completed shows that still lack data.
Schedule::command('whatnot:sync-show-index --limit=200 --enrich=3')
    ->skip($whatnotPaused)
    ->cron('*/10 * * * *')
    ->name('whatnot-show-index')
    ->withoutOverlapping(20)
    ->onSuccess(fn () => Setting::set('whatnot_last_import_success_at', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_last_import_failure_at', now()->toISOString()));

// Backfill item/order detail once a discovered show is no longer future-dated.
// Show discovery now stores the Whatnot UUID/detail URL before the stream ends.
Schedule::command('whatnot:import-orders --new-only')
    ->skip($whatnotPaused)
    ->cron('22 * * * *')
    ->name('whatnot-import-orders-backfill')
    ->withoutOverlapping(240);

// Daily Whatnot ledger pull — grabs the last 30 days for financial data.
Schedule::command('whatnot:import-ledger --days=30')
    ->skip($whatnotPaused)
    ->cron('52 4 * * *')
    ->name('whatnot-ledger-daily')
    ->withoutOverlapping(240);

// Weekly historical ledger backfill.
Schedule::command('whatnot:import-ledger --days=1825')
    ->skip($whatnotPaused)
    ->cron('0 1 * * 0')
    ->name('whatnot-ledger-backfill-annual')
    ->withoutOverlapping(480);

// Do not schedule the legacy analytics-first import or direct shipment refresh.
// Both used fresh protected-route navigations. The integrated show-index flow above
// follows the exact working Seller Hub SPA path instead and owns analytics/shipments.

Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');

Schedule::command('inventory:snapshot-value')
    ->dailyAt('23:50')
    ->name('inventory-snapshot-value')
    ->withoutOverlapping();
