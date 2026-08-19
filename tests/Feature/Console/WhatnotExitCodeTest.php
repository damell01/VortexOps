<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Tests\TestCase;

/**
 * The scraper's exit code is its only structured channel back to PHP, and for a
 * long time everything non-zero that wasn't 1 was reported as "page selectors
 * didn't match". That sent whoever read the ingestion log hunting through
 * scripts/whatnot-scraper.cjs for a login form Cloudflare had never served.
 *
 * These lock in which explanation each code produces.
 */
class WhatnotExitCodeTest extends TestCase
{
    private function throwFor(int $exitCode, string $stderr = ''): ?\RuntimeException
    {
        $scraper = new class extends WhatnotScraper
        {
            public function classify(int $exitCode, string $stderr): void
            {
                $this->throwForExitCode($exitCode, $stderr);
            }
        };

        try {
            $scraper->classify($exitCode, $stderr);
        } catch (\RuntimeException $e) {
            return $e;
        }

        return null;
    }

    public function test_a_bot_challenge_is_reported_as_a_session_problem(): void
    {
        $e = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED);

        $this->assertNotNull($e);
        $this->assertStringContainsString('session expired', $e->getMessage());
        $this->assertStringContainsString('whatnot:login', $e->getMessage());
        $this->assertStringNotContainsString('selectors', $e->getMessage());
    }

    public function test_rate_limiting_says_to_wait_rather_than_retry(): void
    {
        $e = $this->throwFor(WhatnotScraper::EXIT_RATE_LIMITED);

        $this->assertNotNull($e);
        $this->assertStringContainsString('rate limiting', $e->getMessage());
        $this->assertStringNotContainsString('selectors', $e->getMessage());
    }

    public function test_a_real_selector_miss_still_points_at_the_scraper_script(): void
    {
        $e = $this->throwFor(WhatnotScraper::EXIT_SELECTOR_MISS);

        $this->assertNotNull($e);
        $this->assertStringContainsString('scripts/whatnot-scraper.cjs', $e->getMessage());
    }

    public function test_codes_it_does_not_handle_are_left_to_the_caller(): void
    {
        $this->assertNull($this->throwFor(0));
        $this->assertNull($this->throwFor(1));
    }

    public function test_the_scrapers_own_diagnostics_are_carried_into_the_message(): void
    {
        $e = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED, "\nRay ID: a2d958538f57fef2\n\nblocked: BOT_CHALLENGE\n");

        $this->assertNotNull($e);
        $this->assertStringContainsString('Ray ID: a2d958538f57fef2', $e->getMessage());
        $this->assertStringContainsString('blocked: BOT_CHALLENGE', $e->getMessage());
    }
}
