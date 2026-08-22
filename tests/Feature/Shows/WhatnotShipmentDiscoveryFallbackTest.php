<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Shipment sync was discovering shows from a page it does not need.
 *
 * Shipments are addressed per show — /dashboard/shipments?source=<live_id> —
 * and every imported show carries that id in its detail_url. The live-page
 * discovery only ever saved us from having imported the shows first, so when
 * /dashboard/lives stopped being served the sync returned nothing for a reason
 * that had nothing to do with shipments.
 */
class WhatnotShipmentDiscoveryFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = 'a0a97cbb-097e-4c1f-9174-98ad66937e14';

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->channel = WhatnotChannel::create([
            'name'              => 'Vortex Cards',
            'whatnot_username'  => 'vortexcards',
            'status'            => 'active',
        ]);
    }

    private function show(): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => '2026-08-20',
            'detail_url'         => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            'created_by'         => 1,
        ]);
    }

    private function engineWith(WhatnotScraper $scraper): WhatnotSyncEngine
    {
        $this->app->instance(WhatnotScraper::class, $scraper);

        return app(WhatnotSyncEngine::class);
    }

    public function test_empty_discovery_falls_back_to_shows_already_on_record(): void
    {
        $this->show();

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('fetchShipmentsFromLivePage')->once()->andReturn([]);
        $scraper->shouldReceive('refreshShipmentsForShows')
            ->once()
            ->andReturn(['updated' => 7, 'skipped_shows' => 0]);

        $result = $this->engineWith($scraper)->syncShipmentsFromLivePage($this->channel);

        $this->assertSame(7, $result['updated']);
        $this->assertSame(1, $result['shows_synced']);
    }

    public function test_the_fallback_only_runs_when_discovery_found_nothing(): void
    {
        // Discovery working is still the better path — it sees shows that have
        // not been imported yet, which the database by definition cannot.
        $show = $this->show();

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('fetchShipmentsFromLivePage')
            ->once()
            ->andReturn([self::UUID => [['whatnot_order_id' => 'A1']]]);
        $scraper->shouldNotReceive('refreshShipmentsForShows');
        $scraper->shouldReceive('persistShowOrders')->andReturn(['updated' => 1]);
        $scraper->shouldReceive('persistShipments')->andReturn(['created' => 1]);

        $result = $this->engineWith($scraper)->syncShipmentsFromLivePage($this->channel);

        $this->assertSame(2, $result['updated']);
        $this->assertSame($show->id, Show::first()->id);
    }

    public function test_nothing_imported_yet_means_there_is_nothing_to_fall_back_to(): void
    {
        // No shows means no live_ids, so there is no second route to try — and
        // calling the batch scrape with an empty list would just open a browser
        // to do nothing with it.
        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('fetchShipmentsFromLivePage')->once()->andReturn([]);
        $scraper->shouldNotReceive('refreshShipmentsForShows');

        $result = $this->engineWith($scraper)->syncShipmentsFromLivePage($this->channel);

        $this->assertSame(0, $result['updated']);
    }

    public function test_shows_skipped_for_a_missing_live_id_are_not_counted_as_synced(): void
    {
        $this->show();

        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Manually added, no livestream id',
            'show_date'          => '2026-08-19',
            'detail_url'         => 'https://www.whatnot.com/live/some-slug',
            'created_by'         => 1,
        ]);

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('fetchShipmentsFromLivePage')->once()->andReturn([]);
        $scraper->shouldReceive('refreshShipmentsForShows')
            ->once()
            ->andReturn(['updated' => 3, 'skipped_shows' => 1]);

        $result = $this->engineWith($scraper)->syncShipmentsFromLivePage($this->channel);

        $this->assertSame(1, $result['shows_synced']);
    }
}
