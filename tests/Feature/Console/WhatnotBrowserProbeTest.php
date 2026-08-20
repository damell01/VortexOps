<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Mockery;
use Tests\TestCase;

/**
 * Whatnot's rules here are scoped to paths, not to the connection.
 *
 * The same profile is served /seller with a 200 and refused / with a 403 in the
 * same session, seconds apart. That distinction decides what to do next, and
 * getting it wrong is expensive in both directions: read as a blanket block it
 * sends you buying proxies for an address that was never the problem, and read
 * as nothing at all it sends you tuning a browser that was already acceptable.
 *
 * So the command has to say which of the three shapes it saw.
 */
class WhatnotBrowserProbeTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $results */
    private function probing(array $results): \Illuminate\Testing\PendingCommand
    {
        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('probePathsInBrowser')->andReturn($results);

        $this->app->instance(WhatnotScraper::class, $scraper);

        return $this->artisan('whatnot:probe', ['--browser' => true]);
    }

    private function page(string $url, int $status, bool $challenged): array
    {
        return ['url' => $url, 'status' => $status, 'challenged' => $challenged, 'landedOn' => $url];
    }

    public function test_a_mixed_result_is_reported_as_path_scoped(): void
    {
        $this->probing([
            $this->page('https://www.whatnot.com/seller', 200, false),
            $this->page('https://www.whatnot.com/', 403, true),
        ])
            ->expectsOutputToContain('path-scoped')
            ->expectsOutputToContain('served:  /seller')
            ->expectsOutputToContain('refused: /')
            ->assertSuccessful();
    }

    public function test_a_mixed_result_says_the_address_is_not_the_problem(): void
    {
        // The conclusion that matters: if the address or the browser were what
        // was being judged, nothing would have been served.
        $this->probing([
            $this->page('https://www.whatnot.com/seller', 200, false),
            $this->page('https://www.whatnot.com/', 403, true),
        ])->expectsOutputToContain('both acceptable');
    }

    public function test_a_total_refusal_points_at_the_connection_instead(): void
    {
        $this->probing([
            $this->page('https://www.whatnot.com/seller', 403, true),
            $this->page('https://www.whatnot.com/', 403, true),
        ])
            ->expectsOutputToContain('not path-scoped')
            ->expectsOutputToContain('WHATNOT_PROXY')
            ->assertFailed();
    }

    public function test_everything_served_is_not_reported_as_a_problem(): void
    {
        $this->probing([
            $this->page('https://www.whatnot.com/seller', 200, false),
            $this->page('https://www.whatnot.com/', 200, false),
        ])
            ->expectsOutputToContain('Every page was served')
            ->assertSuccessful();
    }

    public function test_a_challenge_page_counts_as_refused_even_with_a_200(): void
    {
        // Cloudflare's interactive challenge is served as a normal 200 page, so
        // reading the status alone would record it as working.
        $this->probing([
            $this->page('https://www.whatnot.com/seller', 200, true),
            $this->page('https://www.whatnot.com/', 200, true),
        ])
            ->expectsOutputToContain('not path-scoped')
            ->assertFailed();
    }
}
