<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use App\Support\WhatnotBrowserLock;
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
        WhatnotBrowserLock::forceRelease();

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

    public function test_the_holder_is_readable_while_the_lock_is_held_and_gone_after(): void
    {
        // The holder used to live in a second cache key written just after the
        // lock was taken — two facts that could disagree, and did: any job whose
        // finally ran without ever having held the lock deleted the key
        // belonging to the job that did, leaving it "held by nobody".
        //
        // It is now the lock's own owner token, so it cannot drift from the lock
        // and nothing can erase it without releasing the lock.
        $seen = null;

        $this->scraper()->run(function () use (&$seen) {
            $seen = WhatnotBrowserLock::holder();
        });

        $this->assertSame(getmypid(), $seen['pid'] ?? null);
        $this->assertSame(gethostname(), $seen['host'] ?? null);
        $this->assertTrue($seen['alive'] ?? false);

        $this->assertNull(WhatnotBrowserLock::holder());
    }

    public function test_a_lock_held_by_someone_else_still_names_its_holder(): void
    {
        // "Held but no holder recorded" was the symptom that sent people to
        // whatnot:unlock over and over. There is no such state now: whoever
        // holds it is written into the lock itself.
        WhatnotBrowserLock::make(60)->get();

        $holder = WhatnotBrowserLock::holder();

        $this->assertSame(getmypid(), $holder['pid'] ?? null);
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
