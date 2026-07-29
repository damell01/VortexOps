<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WhatnotScraperTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vortex.whatnot.email'    => 'scraper@example.com',
            'vortex.whatnot.password' => 'secret',
        ]);

        $this->channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    private function mockScraper(int $exitCode, string $stdout, string $stderr = ''): WhatnotScraper
    {
        $process = Mockery::mock(Process::class);
        $process->allows('run')->andReturn($exitCode);
        $process->allows('getExitCode')->andReturn($exitCode);
        $process->allows('getOutput')->andReturn($stdout);
        $process->allows('getErrorOutput')->andReturn($stderr);
        $process->allows('isSuccessful')->andReturn($exitCode === 0);

        $scraper = Mockery::mock(WhatnotScraper::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $scraper->allows('makeProcess')->andReturn($process);

        return $scraper;
    }

    private function showRow(array $overrides = []): array
    {
        return array_merge([
            'title'                  => 'Test Break Show',
            'show_date'              => '2026-06-15',
            'show_duration'          => 90,
            'gross_revenue'          => 1500.00,
            'whatnot_net'            => 1350.00,
            'tips'                   => 25.00,
            'units_sold'             => 40,
            'completed_earnings'     => 1400.00,
            'avg_order_value'        => 37.50,
            'giveaway_spend'         => 50.00,
            'giveaways_count'        => 3,
            'buyers_count'           => 42,
            'first_time_buyers'      => 10,
            'returning_buyers'       => 32,
            'shares_count'           => 15,
            'max_concurrent_viewers' => 120,
            'total_views'            => 500,
            'avg_order_rating'       => 4.85,
            'detail_url'             => 'https://www.whatnot.com/show/abc123',
        ], $overrides);
    }

    // ── fetchShows ────────────────────────────────────────────────────────────

    public function test_fetch_shows_throws_when_credentials_missing(): void
    {
        config(['vortex.whatnot.email' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/WHATNOT_EMAIL/');

        app(WhatnotScraper::class)->fetchShows();
    }

    public function test_fetch_shows_returns_parsed_json_on_success(): void
    {
        $rows    = [$this->showRow(), $this->showRow(['title' => 'Second Show'])];
        $scraper = $this->mockScraper(0, json_encode($rows));

        $result = $scraper->fetchShows();

        $this->assertCount(2, $result);
        $this->assertEquals('Test Break Show', $result[0]['title']);
        $this->assertEquals('Second Show', $result[1]['title']);
    }

    public function test_fetch_shows_returns_empty_array_on_blank_stdout(): void
    {
        $scraper = $this->mockScraper(0, '');

        $this->assertEquals([], $scraper->fetchShows());
    }

    public function test_fetch_shows_throws_on_exit_code_two_selector_miss(): void
    {
        $scraper = $this->mockScraper(2, '', 'Selector not found');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/selectors didn't match/");

        $scraper->fetchShows();
    }

    public function test_fetch_shows_throws_on_nonzero_exit_code(): void
    {
        $scraper = $this->mockScraper(1, '', 'Login failed: invalid credentials');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Login failed/');

        $scraper->fetchShows();
    }

    public function test_fetch_shows_throws_on_invalid_json(): void
    {
        $scraper = $this->mockScraper(0, 'not json at all');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid JSON/');

        $scraper->fetchShows();
    }

    public function test_fetch_shows_logs_stderr_even_on_success(): void
    {
        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]), 'Warning: slow page load');

        // Should not throw; stderr is just logged as a warning
        $result = $scraper->fetchShows();
        $this->assertCount(1, $result);
    }

    // ── importShows ───────────────────────────────────────────────────────────

    public function test_import_creates_new_show(): void
    {
        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));

        $counts = $scraper->importShows($this->channel);

        $this->assertEquals(['created' => 1, 'updated' => 0, 'skipped' => 0, 'ordersCreated' => 0], $counts);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertNotNull($show);
        $this->assertEquals('2026-06-15', $show->show_date->toDateString());
        $this->assertEquals($this->channel->id, $show->whatnot_channel_id);
        $this->assertEquals(1500.00, (float) $show->gross_revenue);
        $this->assertEquals('auto_whatnot', $show->import_source);
        $this->assertEquals('draft', $show->status);
    }

    public function test_import_persists_start_and_end_time(): void
    {
        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'start_time' => '19:00:00',
            'end_time'   => '20:30:00',
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertEquals('19:00:00', $show->start_time);
        $this->assertEquals('20:30:00', $show->end_time);
    }

    public function test_import_updates_financial_fields_on_existing_show(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1000.00,
            'whatnot_net'        => 900.00,
            'tips'               => 10.00,
            'units_sold'         => 20,
            'show_duration'      => 60,
            'import_source'      => 'auto_whatnot',
            'status'             => 'reconciled',
            'created_by'         => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'gross_revenue' => 1500.00,
            'units_sold'    => 40,
        ])]));

        $counts = $scraper->importShows($this->channel);

        $this->assertEquals(['created' => 0, 'updated' => 1, 'skipped' => 0, 'ordersCreated' => 0], $counts);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertEquals(1500.00, (float) $show->gross_revenue);
        $this->assertEquals(40, $show->units_sold);
        $this->assertEquals('reconciled', $show->status); // status not overwritten
    }

    public function test_import_attaches_a_streamer_to_a_new_show_when_the_title_matches(): void
    {
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'title' => 'Josh Break Night',
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Josh Break Night')->first();
        $this->assertCount(1, $show->streamers);
        $this->assertEquals('Josh', $show->streamers->first()->name);
    }

    public function test_import_retries_streamer_detection_on_an_existing_show_with_no_streamer(): void
    {
        $existing = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Josh Break Night',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1000.00,
            'import_source'      => 'auto_whatnot',
            'status'             => 'draft',
            'created_by'         => 1,
        ]);
        $this->assertCount(0, $existing->streamers);

        // The streamer didn't exist yet at the time of the first import — it
        // does now, so a re-scrape should catch the match this time.
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'title'         => 'Josh Break Night',
            'gross_revenue' => 1500.00,
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Josh Break Night')->first();
        $this->assertCount(1, $show->streamers);
        $this->assertEquals('Josh', $show->streamers->first()->name);
    }

    public function test_import_flags_financials_revised_when_locked_show_numbers_change(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1000.00,
            'whatnot_net'        => 900.00,
            'tips'               => 10.00,
            'units_sold'         => 20,
            'import_source'      => 'auto_whatnot',
            'status'             => 'reconciled',
            'created_by'         => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'gross_revenue' => 1500.00,
            'units_sold'    => 40,
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertTrue((bool) $show->financials_revised_after_lock);
        $this->assertStringContainsString('gross_revenue: 1000 → 1500', $show->revision_notes);
        $this->assertStringContainsString('units_sold: 20 → 40', $show->revision_notes);
    }

    public function test_import_does_not_flag_financials_revised_for_shows_still_in_review(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1000.00,
            'units_sold'         => 20,
            'import_source'      => 'auto_whatnot',
            'status'             => 'pending_review',
            'created_by'         => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'gross_revenue' => 1500.00,
            'units_sold'    => 40,
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertFalse((bool) $show->financials_revised_after_lock);
        $this->assertNull($show->revision_notes);
    }

    public function test_import_does_not_flag_financials_revised_for_negligible_change(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1500.00,
            'whatnot_net'        => 1350.00,
            'tips'               => 25.00,
            'units_sold'         => 40,
            'import_source'      => 'auto_whatnot',
            'status'             => 'reconciled',
            'created_by'         => 1,
        ]);

        // Identical figures to the current row — no real revision, just a re-scrape.
        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'gross_revenue' => 1500.00,
            'units_sold'    => 40,
        ])]));

        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertFalse((bool) $show->financials_revised_after_lock);
    }

    public function test_import_does_not_overwrite_non_financial_fields(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'gross_revenue'      => 1000.00,
            'whatnot_net'        => 900.00,
            'tips'               => 10.00,
            'units_sold'         => 20,
            'show_duration'      => 60,
            'import_source'      => 'auto_whatnot',
            'status'             => 'reconciled',
            'notes'              => 'Hand-written ops note',
            'created_by'         => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertEquals('reconciled', $show->status);
        $this->assertEquals('Hand-written ops note', $show->notes);
    }

    public function test_import_skips_rows_with_no_title_and_no_date(): void
    {
        $scraper = $this->mockScraper(0, json_encode([
            ['title' => '', 'show_date' => ''],
            ['title' => null, 'show_date' => null],
        ]));

        $counts = $scraper->importShows($this->channel);

        $this->assertEquals(['created' => 0, 'updated' => 0, 'skipped' => 2, 'ordersCreated' => 0], $counts);
        $this->assertEquals(0, Show::count());
    }

    public function test_import_handles_multiple_shows(): void
    {
        $rows    = [
            $this->showRow(['title' => 'Show A', 'show_date' => '2026-06-01']),
            $this->showRow(['title' => 'Show B', 'show_date' => '2026-06-02']),
            $this->showRow(['title' => 'Show C', 'show_date' => '2026-06-03']),
        ];
        $scraper = $this->mockScraper(0, json_encode($rows));

        $counts = $scraper->importShows($this->channel);

        $this->assertEquals(['created' => 3, 'updated' => 0, 'skipped' => 0, 'ordersCreated' => 0], $counts);
        $this->assertEquals(3, Show::where('import_source', 'auto_whatnot')->count());
    }

    public function test_import_without_channel_sets_no_channel_id(): void
    {
        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows(null);

        $this->assertNull(Show::first()->whatnot_channel_id);
    }

    public function test_import_returns_empty_counts_when_scraper_returns_nothing(): void
    {
        $scraper = $this->mockScraper(0, '');

        $counts = $scraper->importShows($this->channel);

        $this->assertEquals(['created' => 0, 'updated' => 0, 'skipped' => 0, 'ordersCreated' => 0], $counts);
    }

    // ── Channel attribution ───────────────────────────────────────────────────

    public function test_import_flags_show_when_matched_under_different_channel(): void
    {
        $otherChannel = WhatnotChannel::create(['name' => 'Other Channel', 'status' => 'active']);

        // Show first attributed to $otherChannel.
        Show::create([
            'whatnot_channel_id' => $otherChannel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'import_source'      => 'auto_whatnot',
            'status'             => 'draft',
            'created_by'         => 1,
        ]);

        // Now the same show is returned by an import running for $this->channel.
        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertTrue((bool) $show->channel_attribution_suspect, 'cross-channel match should be flagged');
        // Original attribution is kept — we do not silently re-stamp the channel.
        $this->assertEquals($otherChannel->id, $show->whatnot_channel_id);
    }

    public function test_import_does_not_flag_show_matched_under_same_channel(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Break Show',
            'show_date'          => '2026-06-15',
            'import_source'      => 'auto_whatnot',
            'status'             => 'draft',
            'created_by'         => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertFalse((bool) $show->channel_attribution_suspect);
    }

    public function test_import_does_not_flag_show_with_no_prior_channel(): void
    {
        // Existing show with no channel yet (e.g. created before channel tagging).
        Show::create([
            'title'         => 'Test Break Show',
            'show_date'     => '2026-06-15',
            'import_source' => 'auto_whatnot',
            'status'        => 'draft',
            'created_by'    => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows($this->channel);

        $show = Show::where('title', 'Test Break Show')->first();
        $this->assertFalse((bool) $show->channel_attribution_suspect);
    }

    // ── Analytics fields ──────────────────────────────────────────────────────

    public function test_import_persists_all_analytics_fields(): void
    {
        $scraper = $this->mockScraper(0, json_encode([$this->showRow()]));
        $scraper->importShows($this->channel);

        $show = Show::first();
        $this->assertEquals(1400.00,  (float) $show->completed_earnings);
        $this->assertEquals(37.50,    (float) $show->avg_order_value);
        $this->assertEquals(50.00,    (float) $show->giveaway_spend);
        $this->assertEquals(3,        $show->giveaways_count);
        $this->assertEquals(42,       $show->buyers_count);
        $this->assertEquals(10,       $show->first_time_buyers);
        $this->assertEquals(32,       $show->returning_buyers);
        $this->assertEquals(15,       $show->shares_count);
        $this->assertEquals(120,      $show->max_concurrent_viewers);
        $this->assertEquals(500,      $show->total_views);
        $this->assertEquals(4.85,     (float) $show->avg_order_rating);
        $this->assertEquals('https://www.whatnot.com/show/abc123', $show->detail_url);
    }

    public function test_import_updates_analytics_fields_on_existing_show(): void
    {
        Show::create([
            'title'          => 'Test Break Show',
            'show_date'      => '2026-06-15',
            'buyers_count'   => 10,
            'total_views'    => 100,
            'import_source'  => 'auto_whatnot',
            'created_by'     => 1,
        ]);

        $scraper = $this->mockScraper(0, json_encode([$this->showRow([
            'buyers_count' => 55,
            'total_views'  => 750,
        ])]));
        $scraper->importShows($this->channel);

        $show = Show::first();
        $this->assertEquals(55,  $show->buyers_count);
        $this->assertEquals(750, $show->total_views);
    }

    // ── Order-scrape safety guard ─────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> distinct fake order rows */
    private function fakeOrders(int $n): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'order_id' => "ord-{$i}",
                'buyer'    => "buyer{$i}",
                'item_name' => "Item {$i}",
                'quantity' => 1,
            ];
        }
        return $rows;
    }

    /** A real WhatnotScraper subclass with fetchOrdersForShows() stubbed to a fixed payload. */
    private function scraperReturningOrders(array $ordersByShow): WhatnotScraper
    {
        return new class($ordersByShow) extends WhatnotScraper {
            public function __construct(private array $stubOrders)
            {
                parent::__construct();
            }

            public function fetchOrdersForShows(array $sources, ?string $channelUsername = null, bool $debug = false, ?callable $onProgress = null): array
            {
                return $this->stubOrders;
            }
        };
    }

    private function invokeImportOrders(WhatnotScraper $scraper, Show $show): int
    {
        $method = new ReflectionMethod(WhatnotScraper::class, 'importOrdersForTargets');
        $method->setAccessible(true);

        return $method->invoke($scraper, [['show' => $show, 'live_id' => 'live-1']], null, false);
    }

    public function test_order_guard_skips_when_scrape_far_exceeds_expected(): void
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'      => 'Guarded Show', 'show_date' => '2026-06-15',
            'units_sold' => 10, 'import_source' => 'auto_whatnot', 'created_by' => 1,
        ]);

        // 10 expected → threshold is 10*2+100 = 120; 500 rows must be rejected.
        $scraper = $this->scraperReturningOrders([$show->id => $this->fakeOrders(500)]);

        $created = $this->invokeImportOrders($scraper, $show);

        $this->assertEquals(0, $created);
        $this->assertEquals(0, WhatnotShowOrder::where('show_id', $show->id)->count());
    }

    public function test_order_guard_allows_reasonable_scrape(): void
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'      => 'Normal Show', 'show_date' => '2026-06-15',
            'units_sold' => 10, 'import_source' => 'auto_whatnot', 'created_by' => 1,
        ]);

        $scraper = $this->scraperReturningOrders([$show->id => $this->fakeOrders(8)]);

        $created = $this->invokeImportOrders($scraper, $show);

        $this->assertEquals(8, $created);
        $this->assertEquals(8, WhatnotShowOrder::where('show_id', $show->id)->count());
    }

    public function test_order_guard_disabled_when_expected_count_unknown(): void
    {
        // units_sold = 0 → no reliable expectation, so the guard must not fire.
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'      => 'Unknown Count Show', 'show_date' => '2026-06-15',
            'units_sold' => 0, 'import_source' => 'auto_whatnot', 'created_by' => 1,
        ]);

        $scraper = $this->scraperReturningOrders([$show->id => $this->fakeOrders(5)]);

        $created = $this->invokeImportOrders($scraper, $show);

        $this->assertEquals(5, $created);
    }

    // ── importAllEnabledChannels ──────────────────────────────────────────────

    public function test_import_all_iterates_active_import_enabled_channels(): void
    {
        WhatnotChannel::create(['name' => 'Channel 2', 'status' => 'active', 'include_in_import' => true]);

        $scraper = Mockery::mock(WhatnotScraper::class)->makePartial();
        $scraper->expects('importShows')
            ->twice()
            ->andReturn(['created' => 1, 'updated' => 0, 'skipped' => 0]);

        $result = $scraper->importAllEnabledChannels();

        $this->assertEquals(2, $result['channels']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
    }

    public function test_import_all_skips_channels_with_include_in_import_false(): void
    {
        WhatnotChannel::create(['name' => 'Excluded', 'status' => 'active', 'include_in_import' => false]);

        $scraper = Mockery::mock(WhatnotScraper::class)->makePartial();
        $scraper->expects('importShows')->once()->andReturn(['created' => 1, 'updated' => 0, 'skipped' => 0]);

        $result = $scraper->importAllEnabledChannels();

        $this->assertEquals(1, $result['channels']);
    }

    public function test_import_all_skips_inactive_channels(): void
    {
        WhatnotChannel::create(['name' => 'Inactive', 'status' => 'inactive', 'include_in_import' => true]);

        $scraper = Mockery::mock(WhatnotScraper::class)->makePartial();
        $scraper->expects('importShows')->once()->andReturn(['created' => 1, 'updated' => 0, 'skipped' => 0]);

        $result = $scraper->importAllEnabledChannels();

        $this->assertEquals(1, $result['channels']);
    }

    public function test_import_all_continues_after_single_channel_failure(): void
    {
        WhatnotChannel::create(['name' => 'Channel 2', 'status' => 'active', 'include_in_import' => true]);

        $call    = 0;
        $scraper = Mockery::mock(WhatnotScraper::class)->makePartial();
        $scraper->expects('importShows')
            ->twice()
            ->andReturnUsing(function () use (&$call) {
                $call++;
                if ($call === 1) {
                    return ['created' => 1, 'updated' => 0, 'skipped' => 0];
                }
                throw new \RuntimeException('Scraper failed for channel 2');
            });

        $result = $scraper->importAllEnabledChannels();

        $this->assertEquals(2, $result['channels']);
        $this->assertEquals(1, $result['created']);
    }

    public function test_import_all_returns_zero_when_no_channels_enabled(): void
    {
        $this->channel->update(['include_in_import' => false]);

        $result = app(WhatnotScraper::class)->importAllEnabledChannels();

        $this->assertEquals(['created' => 0, 'updated' => 0, 'skipped' => 0, 'channels' => 0], $result);
    }
}
