<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The shared browser lock stops two Chromium instances opening the same profile
 * and corrupting it. Its failure mode is silence: a run that queues behind a
 * stale lock looks identical to one that is working, for up to twenty minutes.
 */
class WhatnotBrowserLockTest extends TestCase
{
    private function scraper(): WhatnotScraper
    {
        return new class extends WhatnotScraper
        {
            /** @param callable():mixed $fn */
            public function run(callable $fn, int $waitSeconds = 1200): mixed
            {
                return $this->withBrowserLock($fn, $waitSeconds);
            }
        };
    }

    protected function tearDown(): void
    {
        Cache::lock('whatnot:browser')->forceRelease();
        Cache::forget('whatnot:browser:holder_pid');

        parent::tearDown();
    }

    public function test_it_runs_the_callback_when_the_lock_is_free(): void
    {
        $this->assertSame('done', $this->scraper()->run(fn () => 'done'));
    }

    public function test_it_releases_the_lock_afterwards(): void
    {
        $this->scraper()->run(fn () => null);

        // A second run proves the first released; without it this would block.
        $this->assertSame('second', $this->scraper()->run(fn () => 'second'));
    }

    public function test_it_records_and_clears_the_holder_pid(): void
    {
        $seen = null;

        $this->scraper()->run(function () use (&$seen) {
            $seen = Cache::get('whatnot:browser:holder_pid');
        });

        $this->assertSame(getmypid(), $seen);
        $this->assertNull(Cache::get('whatnot:browser:holder_pid'));
    }

    public function test_it_releases_the_lock_even_when_the_callback_throws(): void
    {
        try {
            $this->scraper()->run(fn () => throw new \RuntimeException('scrape blew up'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('after', $this->scraper()->run(fn () => 'after'));
    }

    public function test_it_gives_up_on_a_held_lock_with_advice_rather_than_hanging(): void
    {
        // What a Ctrl+C'd run leaves behind: the lock held, its owner gone.
        Cache::lock('whatnot:browser', 13800)->get();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/whatnot:unlock/');

        $this->scraper()->run(fn () => 'never reached', waitSeconds: 1);
    }

    public function test_a_held_lock_does_not_run_the_callback(): void
    {
        Cache::lock('whatnot:browser', 13800)->get();
        $ran = false;

        try {
            $this->scraper()->run(function () use (&$ran) { $ran = true; }, waitSeconds: 1);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse($ran, 'the callback ran while another holder had the lock');
    }
}
