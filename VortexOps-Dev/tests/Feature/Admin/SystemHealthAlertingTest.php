<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\SystemHealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemHealthAlertingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
    }

    private function insertFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);
    }

    public function test_reports_healthy_when_no_issues(): void
    {
        $this->artisan('health:check')
            ->expectsOutputToContain('System healthy.')
            ->assertSuccessful();
    }

    public function test_detects_failed_jobs(): void
    {
        $this->insertFailedJob();

        $this->artisan('health:check')
            ->expectsOutputToContain('1 failed job(s) in the queue')
            ->assertSuccessful();
    }

    public function test_detects_queue_backlog(): void
    {
        $rows = [];
        for ($i = 0; $i < 101; $i++) {
            $rows[] = ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()];
        }
        DB::table('jobs')->insert($rows);

        $this->artisan('health:check')
            ->expectsOutputToContain('Queue backlog')
            ->assertSuccessful();
    }

    public function test_detects_stale_worker_heartbeat(): void
    {
        Setting::set('worker_last_heartbeat', now()->subMinutes(20)->toISOString());

        $this->artisan('health:check')
            ->expectsOutputToContain('Queue worker heartbeat is stale')
            ->assertSuccessful();
    }

    public function test_detects_stale_whatnot_import_only_when_configured(): void
    {
        Setting::set('whatnot_last_import_success_at', now()->subHours(5)->toISOString());

        // Not flagged — no channel has import enabled yet.
        $this->artisan('health:check')
            ->expectsOutputToContain('System healthy.')
            ->assertSuccessful();

        WhatnotChannel::create(['name' => 'Main', 'status' => 'active', 'include_in_import' => true]);

        $this->artisan('health:check')
            ->expectsOutputToContain("Whatnot import hasn't succeeded")
            ->assertSuccessful();
    }

    // ── Notification routing + throttling ─────────────────────────────────────

    public function test_notify_sends_to_admins_via_notification_router(): void
    {
        Notification::fake();
        $this->insertFailedJob();

        $this->artisan('health:check --notify')->assertSuccessful();

        Notification::assertSentTo($this->admin, SystemHealthAlert::class);
    }

    public function test_notify_throttles_repeat_alerts_within_window(): void
    {
        Notification::fake();
        $this->insertFailedJob();

        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 1);

        // Same issue, run again immediately — should not re-notify.
        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 1);
    }

    public function test_notify_reissues_alert_when_issues_change(): void
    {
        Notification::fake();
        $this->insertFailedJob();
        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 1);

        // A second, different problem — should notify again even though the
        // throttle window for the first issue hasn't elapsed.
        Setting::set('worker_last_heartbeat', now()->subMinutes(20)->toISOString());
        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 2);
    }

    public function test_healthy_run_clears_throttle_state_for_next_incident(): void
    {
        Notification::fake();
        $this->insertFailedJob();
        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 1);

        DB::table('failed_jobs')->truncate();
        $this->artisan('health:check --notify')->assertSuccessful(); // healthy, clears state

        $this->insertFailedJob(); // same signature as the first incident
        $this->artisan('health:check --notify')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, SystemHealthAlert::class, 2);
    }
}
