<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemHealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSystemHealth extends Command
{
    protected $signature = 'health:check {--notify : Send database notification to owner on issues}';
    protected $description = 'Check queue, disk, and failed jobs; notify owner when problems are detected';

    public function handle(): int
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

        // Disk space
        $freeMb = (int) round(disk_free_space(storage_path()) / 1048576);
        if ($freeMb < 500) {
            $issues[] = "Low disk space: {$freeMb} MB free";
        }

        if (empty($issues)) {
            $this->info('System healthy.');
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
        }

        if ($this->option('notify')) {
            $ownerEmail = config('app.owner_email', 'dbellcreations@gmail.com');
            $owner = User::where('email', $ownerEmail)->first();
            if ($owner) {
                $owner->notify(new SystemHealthAlert($issues));
                $this->line("Owner notified via database notification.");
            }
        }

        return self::SUCCESS;
    }
}
