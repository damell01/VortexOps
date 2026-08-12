<?php

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A no-op job the scheduler enqueues every minute. When a running queue worker
 * picks it up it stamps worker_last_heartbeat, giving the System Health page a
 * true "the worker is consuming jobs" signal (not just "a process exists").
 * If the timestamp goes stale, the worker is down or wedged.
 */
class WorkerHeartbeat implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $timeout = 30;
    public int $tries   = 1;

    public function handle(): void
    {
        Setting::set('worker_last_heartbeat', now()->toISOString());
    }
}
