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

// Lightweight show discovery is the heartbeat of the Whatnot integration.
// It intentionally uses the authenticated Seller Hub show-index path rather than
// /account/analytics. That keeps scheduled/live/upcoming shows in VortexOps early,
// keyed by their stable livestream UUID, so the same row is available later for
// orders, shipments and analytics enrichment after the stream ends.
Schedule::command('whatnot:sync-show-index --limit=200')
    ->skip($whatnotPaused)
    ->cron('*/10 * * * *')
    ->name('whatnot-show-index')
    ->withoutOverlapping(20)
    ->onSuccess(fn () => Setting::set('whatnot_last_import_success_at', now()->toISOString()))
    ->onFailure(fn () => Setting::set('whatnot_last_import_failure_at', now()->toISOString()));

// Backfill orders once a discovered show is no longer future-dated. Because show
// discovery stores the Whatnot UUID/detail URL before the stream ends, this job no
// longer depends on analytics successfully rediscovering the show afterward.
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

// The previous scheduled whatnot:import / whatnot:sync-all jobs started with the
// analytics surface that Cloudflare currently refuses from the VPS. Do not let
// those known-broken jobs monopolize the shared browser lock. Show discovery,
// orders, shipments and ledger remain automated independently; analytics can be
// re-enabled as an enrichment job once its endpoint is made reliable again.

// Shipment-detail refresh for shows with unresolved shipments.
Schedule::command('whatnot:sync-shipments')
    ->skip($whatnotPaused)
    ->cron('37 * * * *')
    ->name('whatnot-sync-shipments')
    ->withoutOverlapping(120);

Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');

Schedule::command('inventory:snapshot-value')
    ->dailyAt('23:50')
    ->name('inventory-snapshot-value')
    ->withoutOverlapping();
