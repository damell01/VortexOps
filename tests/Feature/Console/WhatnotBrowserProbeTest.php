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

    // ── The second question: are the app's own requests blocked too? ──────

    public function test_a_fetch_that_gets_through_is_reported_as_a_way_forward(): void
    {
        // A 403 on a navigation is not the same as the connection being
        // refused. If the page's own requests are served, a route that never
        // navigates is worth building — and that is the difference between
        // "keep going" and "buy a proxy".
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [['url' => 'https://www.whatnot.com/dashboard/lives', 'status' => 200, 'bytes' => 48210, 'challenged' => false]],
        )
            ->expectsOutputToContain('Asked for as a fetch from inside /seller')
            ->expectsOutputToContain('get through where a navigation does not');
    }

    public function test_a_fetch_that_is_challenged_too_closes_that_door(): void
    {
        // Saying so plainly matters: it is the result that means the browser
        // work is finished and the remaining variable is the connection.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [['url' => 'https://www.whatnot.com/dashboard/lives', 'status' => 403, 'bytes' => 6000, 'challenged' => true]],
        )->expectsOutputToContain('would not help');
    }

    public function test_a_challenge_served_as_a_200_still_counts_as_blocked(): void
    {
        // Cloudflare's interactive challenge comes back 200, so reading the
        // status alone would record an interstitial as a working page.
        $this->probing(
            [$this->page('https://www.whatnot.com/seller', 200, false)],
            [['url' => 'https://www.whatnot.com/', 'status' => 200, 'bytes' => 5800, 'challenged' => true]],
        )->expectsOutputToContain('would not help');
    }

    public function test_without_the_flag_nothing_extra_is_reported(): void
    {
        $this->probing([$this->page('https://www.whatnot.com/seller', 200, false)])
            ->doesntExpectOutputToContain('Asked for as a fetch');
    }
}
