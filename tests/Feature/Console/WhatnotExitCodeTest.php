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

    public function test_a_bot_challenge_names_both_causes_and_neither_is_selectors(): void
    {
        config(['vortex.whatnot.headless' => null, 'vortex.whatnot.proxy' => null]);

        $e = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED);

        $this->assertNotNull($e);
        $this->assertStringNotContainsString('selectors', $e->getMessage());

        // A challenge at /login means the session lapsed; a challenge anywhere
        // else means the session is fine and the browser was what got refused.
        // Naming only the first sent us renewing a session that already worked.
        $this->assertStringContainsString('whatnot:login', $e->getMessage());
        $this->assertStringContainsString('WHATNOT_HEADLESS=false', $e->getMessage());
    }

    public function test_it_does_not_suggest_the_step_already_taken(): void
    {
        // Being told to "retry headless-off" on a run that was already headless-off
        // reads as the tool not knowing what it just did, and costs a round trip
        // to find out the run was configured that way all along.
        config(['vortex.whatnot.headless' => false, 'vortex.whatnot.proxy' => null]);

        $e = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED);

        $this->assertNotNull($e);
        $this->assertStringNotContainsString('WHATNOT_HEADLESS=false php artisan', $e->getMessage());
        $this->assertStringContainsString('WHATNOT_PROXY', $e->getMessage());
    }

    public function test_it_offers_the_network_as_the_next_thing_to_try_not_as_the_answer(): void
    {
        // This message used to say the remaining variable *was* the server's IP,
        // reading its own list of ruled-out options as proof of the one left
        // over. A challenge does not identify itself: the page looks the same
        // whether the network, the browser environment or some other client-side
        // signal produced it. Naming a cause here is how an investigation stops
        // at the first plausible story.
        config(['vortex.whatnot.headless' => false, 'vortex.whatnot.proxy' => null]);

        $message = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED)?->getMessage() ?? '';

        $this->assertStringNotContainsString('the remaining variable is', $message);

        // It should still say what to do next, and what trying it would prove.
        $this->assertStringContainsString('candidate', $message);
        $this->assertStringContainsString('browser environment', $message);
        $this->assertStringContainsString('client-side', $message);
    }

    public function test_with_both_already_tried_it_says_so_rather_than_looping(): void
    {
        config(['vortex.whatnot.headless' => false, 'vortex.whatnot.proxy' => 'socks5://127.0.0.1:40000']);

        $e = $this->throwFor(WhatnotScraper::EXIT_AUTH_REQUIRED);

        $this->assertNotNull($e);
        $this->assertStringContainsString('socks5://127.0.0.1:40000', $e->getMessage());
        $this->assertStringContainsString('residential', $e->getMessage());
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
