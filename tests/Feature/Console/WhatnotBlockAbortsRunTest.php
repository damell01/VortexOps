<?php

namespace Tests\Feature\Console;

use App\Exceptions\WhatnotBlockedException;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * A block is about this machine, not about one channel.
 *
 * Every channel goes through the same Chromium profile to the same Cloudflare
 * edge, so a refusal aimed at the connection refuses all four identically.
 * Carrying on took the same refusal four times — a quarter of an hour of
 * waiting out interstitials — and printed the same wall of diagnosis after each
 * one, which buries the first and only useful copy under three repeats.
 *
 * A failure that really is about one channel still lets the others run.
 */
class WhatnotBlockAbortsRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            WhatnotChannel::create([
                'name'              => $name,
                'whatnot_username'  => strtolower($name),
                'status'            => 'active',
                'include_in_import' => true,
            ]);
        }
    }

    private function scraperThrowing(\Throwable $error): int
    {
        $attempts = 0;

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('importShows')
            ->andReturnUsing(function () use ($error, &$attempts) {
                $attempts++;
                throw $error;
            });

        $this->app->instance(WhatnotScraper::class, $scraper);

        $this->artisan('whatnot:import', ['--limit' => 1]);

        return $attempts;
    }

    public function test_a_block_stops_the_run_at_the_first_channel(): void
    {
        $this->assertSame(
            1,
            $this->scraperThrowing(new WhatnotBlockedException('Cloudflare blocked the scraper.')),
            'The other channels would have been refused the same way.',
        );
    }

    public function test_the_channels_it_skipped_are_still_accounted_for(): void
    {
        // Silently dropping them would read as them having succeeded.
        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('importShows')->andThrow(new WhatnotBlockedException('blocked'));
        $this->app->instance(WhatnotScraper::class, $scraper);

        $this->artisan('whatnot:import', ['--limit' => 1])
            ->expectsOutputToContain('Stopping here')
            ->expectsOutputToContain('remaining 2');
    }

    public function test_an_ordinary_failure_still_lets_the_rest_try(): void
    {
        // A selector miss is about one page. The next channel may well work,
        // and stopping on it would turn one bad page into no import at all.
        $this->assertSame(
            3,
            $this->scraperThrowing(new \RuntimeException('page selectors did not match')),
        );
    }
}
