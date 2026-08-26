<?php

namespace Tests\Feature\Shows;

use App\Console\Commands\RefreshRecentWhatnotShows;
use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * What happens when the browser lock is already held.
 *
 * Only one Whatnot job may drive the browser at a time. Two things went wrong
 * when a second one arrived:
 *
 * A run that timed out waiting still printed its completion table — twenty
 * shows selected, zero of everything else — and exited SUCCESS. That reads
 * exactly like a run that found nothing to do, so a backfill on top of it could
 * only infer the truth from the outstanding count refusing to move.
 *
 * Worse, its `finally` block cleared `whatnot:browser:holder_pid` on the way
 * out — a key belonging to the job that actually held the lock. That is how the
 * lock ends up held with no recorded holder, the state whatnot:unlock exists to
 * explain, and the second run caused it rather than finding it.
 */
class WhatnotBrowserLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = WhatnotChannel::create([
            'name'              => 'Vortex Breaks',
            'status'            => 'active',
            'include_in_import' => true,
        ]);

        Show::create([
            'whatnot_channel_id' => $channel->id,
            'whatnot_show_id'    => (string) str()->uuid(),
            'title'              => 'Break #900',
            'show_date'          => now()->subDays(5)->toDateString(),
            'status'             => 'mapping',
        ]);
    }

    /** Hold the lock the way a running job does, and record its PID. */
    private function holdTheLock(): void
    {
        Cache::lock('whatnot:browser', 1800)->get();
        Cache::put('whatnot:browser:holder_pid', 424242, 1800);
    }

    public function test_a_locked_out_run_does_not_report_success(): void
    {
        $this->holdTheLock();

        $this->artisan('whatnot:refresh-recent --limit=1')
            ->expectsOutputToContain('another browser job is still running')
            ->assertExitCode(RefreshRecentWhatnotShows::SKIPPED_LOCKED);
    }

    public function test_a_locked_out_run_prints_no_completion_table(): void
    {
        // The zero-filled table was the misleading part: it looked like work.
        $this->holdTheLock();

        $this->artisan('whatnot:refresh-recent --limit=1')
            ->doesntExpectOutputToContain('Recent Whatnot refresh complete')
            ->assertExitCode(RefreshRecentWhatnotShows::SKIPPED_LOCKED);
    }

    public function test_a_locked_out_run_leaves_the_real_holders_pid_alone(): void
    {
        $this->holdTheLock();

        $this->artisan('whatnot:refresh-recent --limit=1')
            ->assertExitCode(RefreshRecentWhatnotShows::SKIPPED_LOCKED);

        $this->assertSame(
            424242,
            Cache::get('whatnot:browser:holder_pid'),
            'the locked-out run erased the PID of the job that actually holds the lock',
        );
    }

    public function test_the_backfill_says_the_lock_is_why_rather_than_guessing(): void
    {
        $this->holdTheLock();

        $this->artisan('whatnot:backfill-history --batches=1 --sleep=0')
            ->expectsOutputToContain('the browser lock is held')
            ->expectsOutputToContain('whatnot:unlock')
            ->assertFailed();
    }
}
