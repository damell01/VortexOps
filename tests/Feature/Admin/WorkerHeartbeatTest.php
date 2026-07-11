<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SystemHealth;
use App\Jobs\WorkerHeartbeat;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The worker heartbeat gives the System Health page a real "the queue is being
 * drained" signal: the job stamps a timestamp, and the page reads its freshness.
 */
class WorkerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_stamps_the_worker_heartbeat(): void
    {
        Setting::set('worker_last_heartbeat', null);

        (new WorkerHeartbeat)->handle();

        $this->assertNotNull(Setting::get('worker_last_heartbeat'));
    }

    public function test_health_reports_ok_for_a_fresh_heartbeat(): void
    {
        Setting::set('worker_last_heartbeat', now()->toISOString());

        $status = (new SystemHealth)->getWorkerStatusProperty();

        $this->assertTrue($status['ok']);
    }

    public function test_health_reports_down_for_a_stale_heartbeat(): void
    {
        Setting::set('worker_last_heartbeat', now()->subMinutes(20)->toISOString());

        $status = (new SystemHealth)->getWorkerStatusProperty();

        $this->assertFalse($status['ok']);
        $this->assertStringContainsString('down', $status['label']);
    }

    public function test_health_reports_unknown_when_no_heartbeat_yet(): void
    {
        Setting::set('worker_last_heartbeat', null);

        $status = (new SystemHealth)->getWorkerStatusProperty();

        $this->assertNull($status['ok']);
    }
}
