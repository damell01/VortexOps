<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => Setting::set('scheduler_last_heartbeat', now()->toISOString()))->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('02:00');
Schedule::command('health:check --notify')->everyFifteenMinutes();
// Frequent "catch the stream that just ended" import: the analytics walk starts
// at the newest show, so a small limit grabs just-ended shows quickly (dedup
// updates existing ones). Imports each show's orders in the same browser session.
// withoutOverlapping keeps runs from stacking / colliding on the shared browser
// profile — critical since every whatnot:* command drives the same Chromium profile.
Schedule::command('whatnot:import --limit=15')
    ->cron('*/15 * * * *')
    ->name('whatnot-import-recent')
    ->withoutOverlapping(30);

// Order backfill for older shows (beyond the recent window above) that have a
// detail_url but no orders yet. Once an hour at :22 — a quiet minute between the
// :15 and :30 imports — so it can't fight the import for the browser profile.
Schedule::command('whatnot:import-orders --new-only')
    ->cron('22 * * * *')
    ->name('whatnot-import-orders-backfill')
    ->withoutOverlapping(30);

Schedule::command('whatnot:sync')->hourly()->name('whatnot-sync-hourly')->withoutOverlapping(10);
