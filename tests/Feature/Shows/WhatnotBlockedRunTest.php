<?php

namespace Tests\Feature\Shows;

use App\Exceptions\WhatnotBlockedException;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Cloudflare check used to be treated as a passing hiccup: the step was
 * retried and the run moved on to the next channel, each attempt another round
 * of page loads into the same wall. Now the first one ends the run.
 */
class WhatnotBlockedRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cloudflare_check_stops_the_run_without_retrying_or_trying_other_channels(): void
    {
        foreach (['vortexcollects', 'vortexshop'] as $name) {
            WhatnotChannel::create(['name' => $name, 'whatnot_username' => $name, 'status' => 'active', 'include_in_import' => true]);
        }

        $this->mock(WhatnotReportingReconciler::class, function ($mock) {
            $mock->shouldReceive('backfillAnalytics')->once()
                ->andThrow(new WhatnotBlockedException('Whatnot is rate limiting this account. Wait and retry.'));
        });

        $this->artisan('whatnot:sync-reporting', ['--analytics-only' => true])
            ->expectsOutputToContain('Stopped: Whatnot showed a Cloudflare check')
            ->doesntExpectOutputToContain('retrying once')
            ->assertExitCode(0);
    }
}
