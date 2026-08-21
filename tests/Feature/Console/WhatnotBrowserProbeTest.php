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
    private function probing(array $results, array $fetches = []): \Illuminate\Testing\PendingCommand
    {
        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('probePathsInBrowser')
            ->andReturn(['navigations' => $results, 'fetches' => $fetches]);

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

    // ── The second question: which surface is actually protected? ─────────

    private function api(string $url, int $status, bool $challenged, bool $reached = true): array
    {
        return ['url' => $url, 'api' => true, 'status' => $status, 'bytes' => 900,
                'challenged' => $challenged, 'reachedTheApp' => $reached];
    }

    private function pageFetch(string $url, int $status, bool $challenged): array
    {
        return ['url' => $url, 'api' => false, 'status' => $status, 'bytes' => 6000, 'challenged' => $challenged];
    }

    public function test_an_api_that_answers_is_the_route_forward(): void
    {
        // The distinction that decides whether this is over. Page routes being
        // refused is expected — pages are what the rule protects. The API is a
        // different surface, and its answer is the one that matters.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [
                $this->api('https://www.whatnot.com/services/graphql/?operationName=__probe', 400, false),
                $this->pageFetch('https://www.whatnot.com/dashboard/lives', 403, true),
            ],
        )
            ->expectsOutputToContain('The API answers even though the pages do not.')
            ->expectsOutputToContain('load /seller once');
    }

    public function test_a_challenged_api_is_the_end_of_the_road(): void
    {
        // Said plainly, because this is the result that means the browser work
        // is finished and the remaining variable is the connection.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [$this->api('https://www.whatnot.com/services/graphql/?operationName=__probe', 403, true, false)],
        )->expectsOutputToContain('no surface left to read through.');
    }

    public function test_a_graphql_error_still_counts_as_reaching_the_application(): void
    {
        // An endpoint replying "no such operation" is the endpoint working.
        // Only the edge refusing it is a block, and treating a 400 from the app
        // as a failure would retire a route that was never actually shut.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [$this->api('https://www.whatnot.com/services/graphql/?operationName=__probe', 400, false)],
        )->expectsOutputToContain('reached the application');
    }

    public function test_refused_page_routes_do_not_read_as_the_end(): void
    {
        // Pages are what the rule protects, so their refusal is not evidence
        // about the API — and an earlier version of this output said it was.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [$this->pageFetch('https://www.whatnot.com/dashboard/lives', 403, true)],
        )->expectsOutputToContain('the API line above is the one that decides');
    }

    public function test_without_the_flag_nothing_extra_is_reported(): void
    {
        $this->probing([$this->page('https://www.whatnot.com/seller', 200, false)])
            ->doesntExpectOutputToContain('asked for as a fetch');
    }

    public function test_a_page_that_clears_a_challenge_is_reported_as_reachable(): void
    {
        // The distinction the probe was missing entirely. Cloudflare's managed
        // challenge is designed to be passed — it runs, sets a clearance and
        // reloads into the real page. Sampling four seconds in recorded the
        // challenge arriving and called the page blocked, and five rounds of
        // conclusions were drawn from that.
        $this->probing([
            ['url' => 'https://www.whatnot.com/dashboard/home', 'status' => 403,
             'challenged' => false, 'hadChallenge' => true],
        ])
            ->expectsOutputToContain('after clearing a challenge')
            // And counted as served, not refused — the summary is what people
            // read, and it disagreeing with the row above it is worse than
            // either being wrong alone.
            ->expectsOutputToContain('Every page was served');
    }

    public function test_a_challenge_that_never_clears_still_reads_as_blocked(): void
    {
        $this->probing([
            ['url' => 'https://www.whatnot.com/', 'status' => 403,
             'challenged' => true, 'hadChallenge' => true],
        ])->expectsOutputToContain('never cleared');
    }
}
