<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Notifications\SystemHealthAlert;
use App\Services\NotificationRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSystemHealth extends Command
{
    /** How long an unchanged set of issues stays quiet before re-alerting. */
    private const RENOTIFY_AFTER_MINUTES = 60;

    protected $signature = 'health:check {--notify : Notify admins (database + email) on issues}';
    protected $description = 'Check queue, disk, and failed jobs; notify admins when problems are detected';

    public function handle(NotificationRouter $router): int
    {
        $issues = [];

        // Failed jobs
        $failed = DB::table('failed_jobs')->count();
        if ($failed > 0) {
            $issues[] = "{$failed} failed job(s) in the queue";
        }

        // Queue backlog (>100 pending suggests the worker is stuck)
        $pending = DB::table('jobs')->count();
        if ($pending > 100) {
            $issues[] = "Queue backlog: {$pending} pending jobs (worker may be stuck)";
        }

        // Worker liveness — the scheduler enqueues a heartbeat job every minute;
        // a stale stamp means no worker is draining the queue.
        $workerHeartbeat = Setting::get('worker_last_heartbeat');
        if ($workerHeartbeat) {
            $ts = \Carbon\Carbon::parse($workerHeartbeat);
            if ($ts->lt(now()->subMinutes(10))) {
                $issues[] = "Queue worker heartbeat is stale ({$ts->diffForHumans()}) — worker may be down";
            }
        }

        // Whatnot import freshness — the scheduled import stamps a success
        // timestamp. If it hasn't succeeded in a while (and import is actually
        // configured), the scraper is probably broken — logins expire, selectors
        // drift. Only alerts once imports have run and channels are enabled, so
        // it stays quiet on installs that don't use the scraper.
        $lastImportSuccess = Setting::get('whatnot_last_import_success_at');
        $importConfigured  = DB::table('whatnot_channels')->where('include_in_import', true)->exists();
        if ($lastImportSuccess && $importConfigured) {
            $ts = \Carbon\Carbon::parse($lastImportSuccess);
            if ($ts->lt(now()->subHours(2))) {
                $issues[] = "Whatnot import hasn't succeeded since {$ts->diffForHumans()} — the scraper may be failing (check credentials / selectors)";
            }
        }

        // Disk space
        $freeMb = (int) round(disk_free_space(storage_path()) / 1048576);
        if ($freeMb < 500) {
            $issues[] = "Low disk space: {$freeMb} MB free";
        }

        if (empty($issues)) {
            $this->info('System healthy.');
            // Clear any throttle state so a fresh problem later alerts immediately
            // instead of being silently suppressed by a stale prior signature.
            Setting::set('health_alert_last_signature', '');
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
        }

        if ($this->option('notify') && $this->shouldNotify($issues)) {
            $recipients = $router->getRecipients('system_health');

            foreach ($recipients as $user) {
                $user->notify(new SystemHealthAlert($issues));
            }

            $this->line("Notified {$recipients->count()} admin(s) via database + email.");
        }

        return self::SUCCESS;
    }

    /**
     * Throttles repeat alerts: a given set of issues only re-notifies once
     * RENOTIFY_AFTER_MINUTES has passed, so a persistent problem doesn't spam
     * admins every 15 minutes. A *different* set of issues always notifies
     * immediately, since that's new information.
     */
    private function shouldNotify(array $issues): bool
    {
        $signature = md5(implode('|', $issues));
        $lastSignature = Setting::get('health_alert_last_signature', '');
        $lastNotifiedAt = Setting::get('health_alert_last_notified_at');

        $stillFresh = $lastSignature === $signature
            && $lastNotifiedAt
            && \Carbon\Carbon::parse($lastNotifiedAt)->gt(now()->subMinutes(self::RENOTIFY_AFTER_MINUTES));

        if ($stillFresh) {
            return false;
        }

        Setting::set('health_alert_last_signature', $signature);
        Setting::set('health_alert_last_notified_at', now()->toISOString());

        return true;
    }
}
