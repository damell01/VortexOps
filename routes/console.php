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
Schedule::command('db:backup --prune=14')->dailyAt('02:00')->name('daily-db-backup')->withoutOverlapping();
Schedule::command('storage:prune-runtime --days=14 --imports-hours=48 --max-log-mb=25')->dailyAt('03:15')->name('prune-runtime-storage')->withoutOverlapping();
Schedule::command('health:check')->everyThirtyMinutes()->sendOutputTo('/dev/null');
Schedule::command('workflow:notify-state')->everyFifteenMinutes()->name('workflow-state-notifications')->withoutOverlapping(10);
Schedule::command('timekeeping:missed-clock-outs')->everyFifteenMinutes()->name('timekeeping-missed-clock-outs')->withoutOverlapping(10);
Schedule::command('payroll:sync-pay-runs')->hourly()->name('payroll-sync-current-week')->withoutOverlapping(15);
Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])->dailyAt('03:00')->name('prune-ai-interactions')->withoutOverlapping();
Schedule::command('activitylog:clean')->weeklyOn(7, '03:30')->name('clean-activity-log')->withoutOverlapping();

$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);
$whatnotLog = storage_path('logs/whatnot-scheduler.log');

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
    ->appendOutputTo(storage_path('logs/whatnot-nightly-backfill.log'))
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

Schedule::command('shows:auto-assign-streamers')->everyFifteenMinutes()->name('automatic-streamer-assignment')->withoutOverlapping(10)->appendOutputTo(storage_path('logs/streamer-auto-assignment.log'));



// Exact livestream tips: small, serialized batches using the existing Scrapling
// browser lock. Verified zero-tip shows are not rechecked indefinitely.
// Recent shows are revisited after 24 hours; history is checked once.
Schedule::command('whatnot:sync-tips-batch --mode=recent --limit=2 --apply')
    ->appendOutputTo(storage_path('logs/whatnot-tips-sync.log'))
    ->skip($whatnotPaused)
    ->dailyAt('06:20')
    ->timezone('America/Chicago')
    ->name('whatnot-tips-recent')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('whatnot:sync-tips-batch --mode=history --limit=2 --apply')
    ->appendOutputTo(storage_path('logs/whatnot-tips-sync.log'))
    ->skip($whatnotPaused)
    ->dailyAt('12:20')
    ->timezone('America/Chicago')
    ->name('whatnot-tips-history')
    ->withoutOverlapping(180)
    ->runInBackground();
