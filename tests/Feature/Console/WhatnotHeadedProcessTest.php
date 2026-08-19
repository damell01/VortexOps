<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Tests\TestCase;

/**
 * Headed mode is the answer to Cloudflare challenging the browser rather than
 * the session — but it needs an X display, and cron has none. Verified by hand
 * under `xvfb-run`, it would then fail under the scheduler complaining about a
 * display, which looks nothing like the problem it was meant to solve.
 */
class WhatnotHeadedProcessTest extends TestCase
{
    /** @param array<string, string> $env */
    private function commandFor(array $env): string
    {
        $scraper = new class extends WhatnotScraper
        {
            /** @param array<string, string> $env */
            public function commandFor(array $env): string
            {
                return $this->makeProcess($env)->getCommandLine();
            }
        };

        return $scraper->commandFor($env);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // getenv('DISPLAY') leaking in from the machine running the suite would
        // decide these assertions instead of the code under test.
        putenv('DISPLAY');
    }

    public function test_headless_runs_node_directly(): void
    {
        $command = $this->commandFor(['WHATNOT_HEADLESS' => 'true']);

        $this->assertStringNotContainsString('with-xvfb.sh', $command);
    }

    public function test_the_default_is_headless(): void
    {
        $this->assertStringNotContainsString('with-xvfb.sh', $this->commandFor([]));
    }

    public function test_headed_with_no_display_brings_its_own(): void
    {
        $command = $this->commandFor(['WHATNOT_HEADLESS' => 'false']);

        $this->assertStringContainsString('with-xvfb.sh', $command);
    }

    public function test_it_never_wraps_with_xvfb_run(): void
    {
        // xvfb-run executes its command as `"$@" 2>&1`, folding the child's
        // stderr into stdout. Here that destroys the JSON payload and hides
        // every diagnostic at once, so failures came back as a bare exit code.
        foreach ([['WHATNOT_HEADLESS' => 'false'], ['WHATNOT_HEADLESS' => 'true']] as $env) {
            $this->assertStringNotContainsString('xvfb-run', $this->commandFor($env));
        }
    }

    public function test_headed_with_a_display_already_present_is_left_alone(): void
    {
        // Running under `xvfb-run php artisan …` by hand already provides one;
        // wrapping again would nest two X servers for no reason.
        $command = $this->commandFor(['WHATNOT_HEADLESS' => 'false', 'DISPLAY' => ':99']);

        $this->assertStringNotContainsString('with-xvfb.sh', $command);
    }

    public function test_the_scraper_script_is_always_the_thing_being_run(): void
    {
        foreach ([['WHATNOT_HEADLESS' => 'true'], ['WHATNOT_HEADLESS' => 'false']] as $env) {
            $this->assertStringContainsString('whatnot-scraper.cjs', $this->commandFor($env));
        }
    }
}
