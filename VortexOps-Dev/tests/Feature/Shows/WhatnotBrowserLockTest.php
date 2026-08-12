<?php

namespace Tests\Feature\Shows;

use App\Services\WhatnotScraper;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The shared browser lock serializes every Chromium-launching whatnot:* task so
 * concurrent runs can't corrupt the single persistent Chromium profile.
 */
class WhatnotBrowserLockTest extends TestCase
{
    private function invokeLock(WhatnotScraper $scraper, callable $fn, int $wait): mixed
    {
        $method = new ReflectionMethod($scraper, 'withBrowserLock');
        $method->setAccessible(true);

        return $method->invoke($scraper, $fn, $wait);
    }

    public function test_runs_the_callback_and_returns_its_value(): void
    {
        $result = $this->invokeLock(app(WhatnotScraper::class), fn () => 'ran-ok', 2);

        $this->assertEquals('ran-ok', $result);
    }

    public function test_releases_the_lock_after_the_callback_finishes(): void
    {
        $scraper = app(WhatnotScraper::class);

        $this->invokeLock($scraper, fn () => 'first', 2);

        // If the lock were still held this second call would time out and throw.
        $result = $this->invokeLock($scraper, fn () => 'second', 2);
        $this->assertEquals('second', $result);
    }

    public function test_releases_the_lock_even_when_the_callback_throws(): void
    {
        $scraper = app(WhatnotScraper::class);

        try {
            $this->invokeLock($scraper, function () {
                throw new \RuntimeException('boom');
            }, 2);
            $this->fail('expected the callback exception to propagate');
        } catch (\RuntimeException $e) {
            $this->assertEquals('boom', $e->getMessage());
        }

        // Lock must be free again despite the exception.
        $result = $this->invokeLock($scraper, fn () => 'after-throw', 2);
        $this->assertEquals('after-throw', $result);
    }

    public function test_throws_a_friendly_error_when_the_lock_is_already_held(): void
    {
        // Simulate another whatnot task already holding the shared browser lock.
        $held = Cache::lock('whatnot:browser', 1800);
        $this->assertTrue($held->get());

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/shared Whatnot browser lock/');

            $this->invokeLock(app(WhatnotScraper::class), fn () => 'should-not-run', 1);
        } finally {
            $held->forceRelease();
        }
    }
}
