<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A scraper failure has to arrive with its cause attached.
 *
 * Run by hand the scraper explains itself in detail; run through the app the
 * same failure kept arriving as an exit code and an empty diagnostics section,
 * and that absence was read as Cloudflare more than once. This drives the real
 * pipeline — Symfony Process, pipes, streaming, throwForExitCode — against a
 * stub that stands in for the browser, so the plumbing is tested without
 * needing Whatnot, a network, or a browser.
 */
class WhatnotStderrSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private string $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = tempnam(sys_get_temp_dir(), 'wn-stub-') . '.cjs';
    }

    protected function tearDown(): void
    {
        @unlink($this->stub);

        parent::tearDown();
    }

    /**
     * A stand-in for whatnot-scraper.cjs that writes $lines to stderr and exits
     * with $code — exactly the shape of a blocked-page report.
     */
    private function scraperWritingToStderr(string $text, int $code): WhatnotScraper
    {
        file_put_contents($this->stub, sprintf(
            'process.stderr.write(%s, () => process.exit(%d));',
            json_encode($text),
            $code,
        ));

        $stub = $this->stub;

        return new class($stub) extends WhatnotScraper
        {
            public function __construct(private string $stub)
            {
                parent::__construct();
            }

            protected function makeProcess(array $env, int $timeout = 180): \Symfony\Component\Process\Process
            {
                $process = new \Symfony\Component\Process\Process(['node', $this->stub], null, $env);
                $process->setTimeout($timeout);

                return $process;
            }
        };
    }

    public function test_a_blocked_page_report_reaches_the_exception(): void
    {
        $report = "BOT_CHALLENGE: Cloudflare served a challenge.\nCURRENT_URL: https://www.whatnot.com/seller\n";

        try {
            $this->scraperWritingToStderr($report, 3)->fetchShows(1);
            $this->fail('expected the scraper failure to throw');
        } catch (\RuntimeException $e) {
            // The URL is the whole diagnostic — it separates an expired session
            // from a challenged browser, and without it the message is advice
            // with nothing behind it.
            $this->assertStringContainsString('CURRENT_URL: https://www.whatnot.com/seller', $e->getMessage());
        }
    }

    public function test_progress_lines_written_during_the_run_are_delivered(): void
    {
        // The [whatnot] lines are written long before exit, so losing these
        // would point at streaming rather than at the exit flush.
        $report = "[whatnot] browser profile dir: /tmp/x\n[whatnot] cf_clearance: absent\n"
            . "BOT_CHALLENGE: blocked\nCURRENT_URL: https://www.whatnot.com/seller\n";

        $seen = [];

        try {
            $this->scraperWritingToStderr($report, 3)
                ->fetchShows(1, debug: false, channelUsername: null, onProgress: function ($line) use (&$seen) {
                    $seen[] = $line;
                });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNotEmpty($seen, 'no progress lines were streamed at all');
        $this->assertStringContainsString('browser profile dir', implode("\n", $seen));
    }

    public function test_a_large_report_is_not_truncated_on_the_way_through(): void
    {
        // PAGE_TEXT dumps run to hundreds of KB, which is where a pipe drops
        // data if the process exits before the write drains.
        $report = 'BOT_CHALLENGE: blocked\n' . str_repeat('y', 300_000) . "\nCURRENT_URL: https://www.whatnot.com/seller\n";

        try {
            $this->scraperWritingToStderr($report, 3)->fetchShows(1);
            $this->fail('expected the scraper failure to throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('CURRENT_URL: https://www.whatnot.com/seller', $e->getMessage());
        }
    }

    public function test_a_selector_miss_keeps_its_page_evidence(): void
    {
        $report = "SELECTOR_MISS: No shows found on list page.\nPAGE_TEXT:\nSeller Hub — 0 results\n";

        try {
            $this->scraperWritingToStderr($report, 2)->fetchShows(1);
            $this->fail('expected the scraper failure to throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Seller Hub — 0 results', $e->getMessage());
        }
    }
}
