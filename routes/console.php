<?php

use App\Jobs\WorkerHeartbeat;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storage:prune-runtime {--days=14} {--imports-hours=48} {--max-log-mb=25}', function () {
    $days = max(1, (int) $this->option('days'));
    $importsHours = max(1, (int) $this->option('imports-hours'));
    $maxLogBytes = max(1, (int) $this->option('max-log-mb')) * 1024 * 1024;
    $deletedLogs = 0;
    $deletedImports = 0;
    $rotated = 0;

    $logDir = storage_path('logs');
    File::ensureDirectoryExists($logDir);

    foreach (['laravel.log', 'whatnot-scheduler.log'] as $name) {
        $path = $logDir . DIRECTORY_SEPARATOR . $name;
        if (File::exists($path) && File::size($path) > $maxLogBytes) {
            $rotatedPath = $logDir . DIRECTORY_SEPARATOR
                . pathinfo($name, PATHINFO_FILENAME) . '-' . now()->format('Ymd-His') . '.log';
            File::move($path, $rotatedPath);
            File::put($path, '');
            $rotated++;
        }
    }

    $logCutoff = now()->subDays($days)->timestamp;
    foreach (File::allFiles($logDir) as $file) {
        if (in_array($file->getFilename(), ['laravel.log', 'whatnot-scheduler.log'], true)) {
            continue;
        }
        if ($file->getMTime() < $logCutoff) {
            File::delete($file->getPathname());
            $deletedLogs++;
        }
    }

    $disk = Storage::disk('local');
    $importCutoff = now()->subHours($importsHours)->timestamp;
    foreach ($disk->allFiles('imports') as $path) {
        try {
            if ($disk->lastModified($path) < $importCutoff) {
                $disk->delete($path);
                $deletedImports++;
            }
        } catch (\Throwable) {}
    }

    $this->info("Runtime cleanup complete: {$rotated} log(s) rotated, {$deletedLogs} old log/diagnostic file(s) removed, {$deletedImports} stale import file(s) removed.");
})->purpose('Rotate oversized runtime logs and prune stale logs, diagnostics, and uploaded import files');

Schedule::call(fn () => Setting::set('scheduler_last_heartbeat', now()->toISOString()))->everyFiveMinutes()->name('scheduler-heartbeat')->withoutOverlapping();
Schedule::job(new WorkerHeartbeat)->everyFiveMinutes()->name('worker-heartbeat')->withoutOverlapping();
// Fulfillment Center: open a tracking record for shows that just became ready
// to pack, and tell the assigned packer (or the fulfillment admins).
Schedule::call(function () {
    if (\App\Support\AdminModules::isEnabled('fulfillment')) app(\App\Services\FulfillmentQueue::class)->sync();
})->everyTenMinutes()->name('fulfillment-ready-sync')->withoutOverlapping();
Schedule::command('db:backup --prune=7 --keep=4')->dailyAt('02:00')->name('daily-db-backup')->withoutOverlapping();
// Keep the database small: saved API responses, old raw copies, logs and no-op history (App\Console\Commands\StorageTrim).
// Sundays it also rebuilds the trimmed tables so MySQL hands the freed space back to the disk.
Schedule::command('storage:trim --apply')->dailyAt('03:40')->name('storage-trim')->withoutOverlapping(60)->when(fn () => now()->dayOfWeek !== 0);
Schedule::command('storage:trim --apply --optimize')->weeklyOn(0, '03:40')->name('storage-trim-optimize')->withoutOverlapping(120);
Schedule::command('storage:prune-runtime --days=14 --imports-hours=48 --max-log-mb=25')->dailyAt('03:15')->name('prune-runtime-storage')->withoutOverlapping();
Schedule::command('health:check')->everyThirtyMinutes()->sendOutputTo('/dev/null');
Schedule::command('workflow:notify-state')->everyFifteenMinutes()->name('workflow-state-notifications')->withoutOverlapping(10);
Schedule::command('timekeeping:missed-clock-outs')->everyFifteenMinutes()->name('timekeeping-missed-clock-outs')->withoutOverlapping(10);
Schedule::command('payroll:sync-pay-runs')->hourly()->name('payroll-sync-current-week')->withoutOverlapping(15);
Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])->dailyAt('03:00')->name('prune-ai-interactions')->withoutOverlapping();
Schedule::command('activitylog:clean')->weeklyOn(7, '03:30')->name('clean-activity-log')->withoutOverlapping();

$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);
/**
 * A scheduled command's own log file, or /dev/null when the scheduler's user
 * cannot write it (typically a file left owned by root after a command was run
 * by hand as root). The shell's append would otherwise fail and the command
 * would be reported as failing with exit code 2 even though it never ran.
 */
$scheduleLog = function (string $file): string {
    $path = storage_path('logs/' . $file);
    $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));
    return $writable ? $path : '/dev/null';
};

$whatnotLog = $scheduleLog('whatnot-scheduler.log');

// Two authoritative Scrapling pipelines only. The daytime freshness pass keeps
// operational data current without grinding through large historical backlogs.
// If the browser/pipeline is busy it skips cleanly and the next cadence catches up.
// Operational priority: show discovery + analytics freshness. Shipment scraping
// is intentionally paused so browser time goes to yesterday/today show data.
Schedule::exec('nice -n 12 php artisan whatnot:sync-reporting --since=' . now()->subDays(7)->toDateString() . ' --analytics-only --analytics-limit=8 --max-runtime=600 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(15)
    ->timezone('America/Chicago')
    ->unlessBetween('01:00', '04:59')
    ->name('whatnot-recent-analytics-refresh')
    ->withoutOverlapping(20);

// Discovery pass keeps newly ended shows/UUIDs current. Shipment scraping is
// disabled for now; this pass discovers shows only and leaves analytics to the
// dedicated freshness/reconciliation jobs above and below.
Schedule::exec('nice -n 12 php artisan whatnot:sync-reporting --since=' . now()->subDays(7)->toDateString() . ' --show-limit=8 --without-orders --without-shipments --analytics-limit=6 --max-runtime=600 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(45)
    ->timezone('America/Chicago')
    ->unlessBetween('01:00', '04:59')
    ->name('whatnot-recent-show-discovery')
    ->withoutOverlapping(20);

// Reserve 1–5 AM Central for resumable historical show discovery and metrics.
// The command caps every browser process at the remaining overnight window.
Schedule::exec('nice -n 15 php artisan whatnot:backfill-nightly --since=2026-06-01 --timezone=America/Chicago --start=01:00 --end=05:00')
    ->appendOutputTo($scheduleLog('whatnot-nightly-backfill.log'))
    ->skip($whatnotPaused)
    ->dailyAt('01:00')
    ->timezone('America/Chicago')
    ->name('whatnot-nightly-history-backfill')
    ->withoutOverlapping(300)
    ->runInBackground();

// Catch-up: yesterday's and today's ended shows still without analytics get
// checked directly every hour, all day (it skips while the browser is busy,
// and the overnight run works newest shows first). Four hours after a show
// ends with nothing, the owner hears about it.
Schedule::command('whatnot:analytics-catchup')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(30)
    ->name('whatnot-analytics-catchup')
    ->withoutOverlapping(20);

// Alias cleanup is database-only and does not use the Whatnot browser.
Schedule::command('whatnot:repair-shows --apply --skip-sync --aliases-only')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(5)
    ->name('whatnot-show-alias-cleanup')
    ->withoutOverlapping(10);

Schedule::command('ai:ops operations')->cron('25 */6 * * *')->name('ai-ops-background-summary')->withoutOverlapping(10);
Schedule::command('ai:ops cleanup')->dailyAt('04:15')->name('ai-ops-data-cleanup')->withoutOverlapping(10);
Schedule::command('ai:ops weekly')->weeklyOn(1, '07:00')->name('ai-ops-weekly-management-summary')->withoutOverlapping(10);
Schedule::command('reports:scraper-health-digest')->weeklyOn(1, '07:55')->name('scraper-health-digest')->withoutOverlapping();
Schedule::command('reports:pl-summary day')->dailyAt('07:41')->timezone('America/Chicago')->name('daily-pl-summary')->withoutOverlapping();
Schedule::command('reports:pl-summary week')->weeklyOn(1, '07:46')->timezone('America/Chicago')->name('weekly-pl-summary')->withoutOverlapping();
Schedule::command('reports:show-loss-notifications')->everyFiveMinutes()->name('show-loss-notifications')->withoutOverlapping(10);
Schedule::command('reports:losing-shows')->hourlyAt(23)->name('losing-show-alerts')->withoutOverlapping(10);
Schedule::command('reports:weekly-owner-summary')->weeklyOn(1, '07:50')->name('weekly-owner-summary')->withoutOverlapping();
Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');
Schedule::command('shows:tag-titles')->dailyAt('03:40')->name('tag-show-titles')->withoutOverlapping();
Schedule::command('inventory:snapshot-value')->dailyAt('23:50')->name('inventory-snapshot-value')->withoutOverlapping();

Schedule::command('shows:auto-assign-streamers')->everyFifteenMinutes()->name('automatic-streamer-assignment')->withoutOverlapping(10)->appendOutputTo($scheduleLog('streamer-auto-assignment.log'));



// Exact livestream tips: small, serialized batches using the existing Scrapling
// browser lock. Verified zero-tip shows are not rechecked indefinitely.
// Recent shows are revisited after 24 hours; history is checked once.
Schedule::command('whatnot:sync-tips-batch --mode=recent --limit=2 --apply')
    ->appendOutputTo($scheduleLog('whatnot-tips-sync.log'))
    ->skip($whatnotPaused)
    ->dailyAt('06:20')
    ->timezone('America/Chicago')
    ->name('whatnot-tips-recent')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('whatnot:sync-tips-batch --mode=history --limit=2 --apply')
    ->appendOutputTo($scheduleLog('whatnot-tips-sync.log'))
    ->skip($whatnotPaused)
    ->dailyAt('12:20')
    ->timezone('America/Chicago')
    ->name('whatnot-tips-history')
    ->withoutOverlapping(180)
    ->runInBackground();

// Reconcile imported ledger transactions against exact Whatnot orders and shows.
Schedule::command('ledger:match-shows --limit=100000')->hourlyAt(12)->name('ledger-show-auto-matching')->withoutOverlapping(30)->appendOutputTo($scheduleLog('ledger-show-matching.log'));
// Morning nudge when ledger money is still not tied to a show, streamer or house.
// Shows whose earnings changed after their pay was locked (badge + admin alert).
Schedule::command('payroll:changed-after-payout')->hourlyAt(37)->name('payroll-changed-after-payout')->withoutOverlapping(30);
Schedule::command('ledger:unassigned-alert')->dailyAt('08:23')->timezone('America/Chicago')->name('ledger-unassigned-alert')->withoutOverlapping(30);
// Seller API runs separately from the established Playwright scraper. This schedule
// is inert unless all production-only API gates and per-channel switches are enabled.
Schedule::command('whatnot:api-sync-tick --channel=1 --mode=recent')
    ->cron('*/15 * * * *')
    ->name('whatnot-api-1-recent')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=1 --mode=analytics')
    ->cron('*/30 * * * *')
    ->name('whatnot-api-1-analytics')
    ->withoutOverlapping(25)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=1 --mode=finance')
    ->cron('0 * * * *')
    ->name('whatnot-api-1-finance')
    ->withoutOverlapping(45)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=2 --mode=recent')
    ->cron('*/15 * * * *')
    ->name('whatnot-api-2-recent')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=2 --mode=analytics')
    ->cron('*/30 * * * *')
    ->name('whatnot-api-2-analytics')
    ->withoutOverlapping(25)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=2 --mode=finance')
    ->cron('0 * * * *')
    ->name('whatnot-api-2-finance')
    ->withoutOverlapping(45)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=3 --mode=recent')
    ->cron('*/15 * * * *')
    ->name('whatnot-api-3-recent')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=3 --mode=analytics')
    ->cron('*/30 * * * *')
    ->name('whatnot-api-3-analytics')
    ->withoutOverlapping(25)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=3 --mode=finance')
    ->cron('0 * * * *')
    ->name('whatnot-api-3-finance')
    ->withoutOverlapping(45)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=4 --mode=recent')
    ->cron('*/15 * * * *')
    ->name('whatnot-api-4-recent')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=4 --mode=analytics')
    ->cron('*/30 * * * *')
    ->name('whatnot-api-4-analytics')
    ->withoutOverlapping(25)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

Schedule::command('whatnot:api-sync-tick --channel=4 --mode=finance')
    ->cron('0 * * * *')
    ->name('whatnot-api-4-finance')
    ->withoutOverlapping(45)
    ->runInBackground()
    ->skip(fn () => ! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false) || ! config('services.whatnot_seller.contract_verified', false) || ! config('services.whatnot_seller.sync_scheduler_enabled', false));

// Shipment refresh is read-only to Whatnot, but updates local shipment records.
// Inert until the existing verified Seller API production gates are enabled.
Schedule::command('fulfillment:refresh-shipments --days=14 --limit=8')
    ->everyFifteenMinutes()
    ->name('fulfillment-shipment-refresh')
    ->withoutOverlapping(20)
    ->skip(fn () => ! app()->environment('production')
        || ! config('services.whatnot_seller.live_requests_enabled', false)
        || ! config('services.whatnot_seller.contract_verified', false)
        || ! config('services.whatnot_seller.sync_scheduler_enabled', false)
        || ! \App\Services\WhatnotApiOrderApplier::writesAllowed());
