<?php

use App\Jobs\ProcessWhatnotChannelsJob;
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
    foreach ($disk->files('imports') as $path) {
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
Schedule::command('payroll:sync-pay-runs')->hourly()->name('payroll-sync-current-week')->withoutOverlapping(15);
Schedule::command('model:prune', ['--model' => [\App\Models\AiInteraction::class]])->dailyAt('03:00')->name('prune-ai-interactions')->withoutOverlapping();
Schedule::command('activitylog:clean')->weeklyOn(7, '03:30')->name('clean-activity-log')->withoutOverlapping();

$whatnotPaused = fn () => ! config('vortex.whatnot.schedule_enabled', true);
$whatnotLog = storage_path('logs/whatnot-scheduler.log');

// Fast hourly walk: one channel at a time so each seller account is verified,
// refreshed, and finished before the browser moves to the next channel.
Schedule::job(new ProcessWhatnotChannelsJob())
    ->skip($whatnotPaused)
    ->hourlyAt(5)
    ->name('whatnot-hourly-show-analytics-pull')
    ->withoutOverlapping(55);

// Work in useful 20-30 show chunks rather than the old 8-show batches.
Schedule::command('whatnot:backfill-missing-analytics --days=90 --limit=25 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(15)
    ->name('whatnot-missing-analytics-backfill')
    ->withoutOverlapping(45);

Schedule::command('whatnot:refresh-recent --shipments --limit=25 --skip-if-busy')
    ->appendOutputTo($whatnotLog)
    ->skip($whatnotPaused)
    ->hourlyAt(35)
    ->name('whatnot-unresolved-shipments-refresh')
    ->withoutOverlapping(25);

// Keep a wider rolling ledger so cancellations, refunds, fees, and other
// post-show adjustments continue to update reporting after the original show.
Schedule::command('whatnot:refresh-recent --ledger --ledger-days=90 --skip-if-busy')
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

Schedule::command('ai:ops operations')->cron('25 */6 * * *')->name('ai-ops-background-summary')->withoutOverlapping(10);
Schedule::command('ai:ops cleanup')->dailyAt('04:15')->name('ai-ops-data-cleanup')->withoutOverlapping(10);
Schedule::command('ai:ops weekly')->weeklyOn(1, '07:00')->name('ai-ops-weekly-management-summary')->withoutOverlapping(10);
Schedule::command('reports:midweek-report')->weeklyOn(3, '09:00')->name('midweek-report');
Schedule::command('reports:weekly-review-reminder')->weeklyOn(5, '09:00')->name('weekly-review-reminder');
Schedule::command('inventory:snapshot-value')->dailyAt('23:50')->name('inventory-snapshot-value')->withoutOverlapping();
