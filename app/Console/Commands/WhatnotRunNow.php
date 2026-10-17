<?php

namespace App\Console\Commands;

use App\Jobs\RunWhatnotReportingJob;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * The background half of "Run now" on Scraper Status (App\Support\WhatnotRunControl):
 * the same run a queued RunWhatnotReportingJob does, started immediately in
 * its own process instead of waiting for a worker on the whatnot queue.
 */
class WhatnotRunNow extends Command
{
    protected $signature = 'whatnot:run-now
        {mode=catchup : catchup, freshness, analytics, full or test}
        {--user= : Who started it}
        {--run= : The page run id this belongs to}';

    protected $description = 'Run a Whatnot reporting pass now, reporting live to Scraper Status';

    public function handle(): int
    {
        $runId = $this->option('run') ?: null;
        $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
        if ($runId !== null && ($state['run_id'] ?? null) !== $runId) {
            $this->warn('A newer run has replaced this one; nothing to do.');

            return self::SUCCESS;
        }
        Setting::set('whatnot_ui_job', json_encode(array_merge($state, ['pid' => getmypid(), 'host' => gethostname()])));

        $job = new RunWhatnotReportingJob((string) $this->argument('mode'), (int) $this->option('user'), $runId);
        try {
            $job->handle();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Outside a queue the job can't put itself back to wait its turn; say so
        // rather than sit on "waiting" forever.
        $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
        if (($state['run_id'] ?? null) === $runId && ($state['status'] ?? null) === 'waiting') {
            Setting::set('whatnot_ui_job', json_encode(array_merge($state, [
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'error' => 'Another Whatnot run took the scraper first. Press Run now again to stop it and start fresh.',
            ])));
        }

        return self::SUCCESS;
    }
}
