<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemHealthAlert;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CheckSystemHealth extends Command
{
    /** How long an unchanged set of issues stays quiet before re-alerting. */
    private const RENOTIFY_AFTER_MINUTES = 60;

    protected $signature = 'health:check {--notify : Notify super admins only (database + email) on issues}';
    protected $description = 'Check queue, disk, and failed jobs; notify super admins only when problems are detected';

    public function handle(): int
    {
        $issues = [];

        $failed = DB::table('failed_jobs')->count();
        if ($failed > 0) {
            $issues[] = "{$failed} failed job(s) in the queue";
        }

        $pending = DB::table('jobs')->count();
        if ($pending > 100) {
            $issues[] = "Queue backlog: {$pending} pending jobs (worker may be stuck)";
        }

        $workerHeartbeat = Setting::get('worker_last_heartbeat');
        if ($workerHeartbeat) {
            $ts = \Carbon\Carbon::parse($workerHeartbeat);
            if ($ts->lt(now()->subMinutes(10))) {
                $issues[] = "Queue worker heartbeat is stale ({$ts->diffForHumans()}) — worker may be down";
            }
        }

        $lastImportSuccess = Setting::get('whatnot_last_import_success_at');
        $importConfigured  = DB::table('whatnot_channels')->where('include_in_import', true)->exists();
        if ($lastImportSuccess && $importConfigured) {
            $ts = \Carbon\Carbon::parse($lastImportSuccess);
            if ($ts->lt(now()->subHours(2))) {
                $issues[] = "Whatnot import hasn't succeeded since {$ts->diffForHumans()} — the scraper may be failing (check credentials / selectors)";
            }
        }

        $freeMb = (int) round(disk_free_space(storage_path()) / 1048576);
        if ($freeMb < 500) {
            $issues[] = "Low disk space: {$freeMb} MB free";
        }

        if (empty($issues)) {
            $this->info('System healthy.');
            Setting::set('health_alert_last_signature', '');
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
        }

        if ($this->option('notify') && $this->shouldNotify($issues)) {
            $recipients = $this->superAdmins();

            foreach ($recipients as $user) {
                $user->notify(new SystemHealthAlert($issues));
            }

            $this->line("Notified {$recipients->count()} super admin(s) via database + email.");
        }

        return self::SUCCESS;
    }

    /** System-health alerts are intentionally never routed to ordinary admins/users. */
    private function superAdmins(): Collection
    {
        $exists = Role::where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->exists();

        return $exists ? User::role('super_admin')->get() : new Collection();
    }

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
