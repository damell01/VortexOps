<?php

namespace Tests\Feature\Shows;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Clearing the browser lock, and knowing when not to.
 *
 * The lock records the PID of whatever holds it. Asking only whether that PID
 * is alive is not enough: PIDs are recycled, so the number can be reassigned to
 * something with nothing to do with Whatnot — and then the lock is refused for
 * ever and no scrape can run again. The question is what the process actually
 * is, not merely whether one exists.
 *
 * The bias runs one way on purpose. Refusing to unlock costs a support
 * question; releasing a lock that is genuinely held starts a second Chrome
 * against the same profile and corrupts the saved session.
 */
class WhatnotUnlockTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<resource> */
    private array $spawned = [];

    /** @var list<string> */
    private array $profileFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->spawned as $process) {
            @proc_terminate($process, SIGKILL);
            @proc_close($process);
        }

        foreach ($this->profileFiles as $path) {
            @unlink($path);
        }

        $this->spawned      = [];
        $this->profileFiles = [];

        parent::tearDown();
    }

    private function lockHeldBy(int $pid): void
    {
        Cache::lock('whatnot:browser', 1800)->get();
        Cache::put('whatnot:browser:holder_pid', $pid, 1800);
    }

    /**
     * A real live process whose command line we choose.
     *
     * Using the test runner's own PID looked simpler and was a trap: under
     * `--filter WhatnotUnlockTest` the word "Whatnot" appears in phpunit's
     * argv, so the test passed for a reason that had nothing to do with the
     * code, and failed the moment the suite ran unfiltered.
     */
    private function spawn(string $marker): int
    {
        $process = proc_open(
            ['php', '-r', 'sleep(120);', $marker],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );

        $this->assertIsResource($process, 'could not spawn a probe process');

        foreach ($pipes as $pipe) {
            @fclose($pipe);
        }

        $this->spawned[] = $process;

        $pid = proc_get_status($process)['pid'];

        // proc_open goes via a shell on some builds; wait for argv to settle.
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (str_contains((string) @file_get_contents("/proc/{$pid}/cmdline"), $marker)) {
                return $pid;
            }

            usleep(20_000);
        }

        $this->markTestSkipped('could not observe the probe process command line');
    }

    public function test_a_dead_holder_releases(): void
    {
        // PID 0 never names a live process on Linux.
        $this->lockHeldBy(2);
        Cache::put('whatnot:browser:holder_pid', 0, 1800);

        $this->artisan('whatnot:unlock')
            ->expectsOutputToContain('Lock released')
            ->assertSuccessful();

        $this->assertNull(Cache::get('whatnot:browser:holder_pid'));
    }

    public function test_a_live_but_unrelated_pid_is_treated_as_reuse_and_released(): void
    {
        // Alive, but nothing to do with Whatnot. Before this, a recorded PID
        // landing on any live process blocked every future run for ever.
        $pid = $this->spawn('vortexops-probe-unrelated');
        $this->lockHeldBy($pid);

        $this->artisan('whatnot:unlock')
            ->expectsOutputToContain('PID has been reused')
            ->assertSuccessful();

        $this->assertNull(Cache::get('whatnot:browser:holder_pid'));
    }

    public function test_a_live_whatnot_job_is_not_released(): void
    {
        $pid = $this->spawn('scripts/whatnot-production-sync.cjs');
        $this->lockHeldBy($pid);

        $this->artisan('whatnot:unlock')
            ->expectsOutputToContain('still running')
            ->assertFailed();

        $this->assertSame($pid, Cache::get('whatnot:browser:holder_pid'));
    }

    public function test_force_releases_a_live_whatnot_job(): void
    {
        $pid = $this->spawn('scripts/whatnot-production-sync.cjs');
        $this->lockHeldBy($pid);

        $this->artisan('whatnot:unlock --force')
            ->expectsOutputToContain('Lock released')
            ->assertSuccessful();

        $this->assertNull(Cache::get('whatnot:browser:holder_pid'));
    }

    public function test_no_recorded_holder_releases_and_says_so(): void
    {
        Cache::lock('whatnot:browser', 1800)->get();

        $this->artisan('whatnot:unlock')
            ->expectsOutputToContain('no holder PID was recorded')
            ->assertSuccessful();
    }

    public function test_chromes_own_stale_profile_lock_is_cleared_too(): void
    {
        // Two locks over one resource. Releasing only the cache lock let the
        // next run start and then die at launch on "Failed to create a
        // ProcessSingleton for your profile directory" — which reads like a
        // broken install rather than the leftover of a killed run, and no
        // amount of whatnot:unlock ever fixed it.
        $lock = $this->profilePath('SingletonLock');
        @symlink('srv1590821-999999999', $lock);   // a PID that cannot be alive

        $this->artisan('whatnot:unlock')->assertSuccessful();

        $this->assertFalse(is_link($lock), 'Chrome\'s stale profile lock was left behind');
    }

    public function test_a_profile_lock_held_by_a_live_chrome_is_left_alone(): void
    {
        // Deleting this one lets a second Chrome start against the same profile,
        // which is how the saved Whatnot session gets corrupted.
        $pid  = $this->spawn('vortexops-probe-chrome');
        $lock = $this->profilePath('SingletonLock');
        @symlink('srv1590821-' . $pid, $lock);

        $this->artisan('whatnot:unlock')
            ->expectsOutputToContain('Chrome still holds the profile')
            ->assertSuccessful();

        $this->assertTrue(is_link($lock), 'a profile lock held by a live Chrome was removed');
    }

    private function profilePath(string $name): string
    {
        $dir = storage_path('whatnot-browser-profile');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $name;
        @unlink($path);
        $this->profileFiles[] = $path;

        return $path;
    }
}
