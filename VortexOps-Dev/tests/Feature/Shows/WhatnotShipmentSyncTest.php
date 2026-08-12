<?php

namespace Tests\Feature\Shows;

use App\Jobs\SyncWhatnotShipmentsJob;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Task #28: the shipments-page scraper enriches (never creates) existing orders
 * matched by whatnot_order_id with weight/dims/carrier/shipping-status. These
 * tests exercise persistShowOrders()'s merge path directly with synthetic rows
 * shaped like extractShipmentMeta()'s output — no live scraping involved — plus
 * WhatnotSyncEngine's show-selection logic for the 30-minute refresh job.
 */
class WhatnotShipmentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function makeShow(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'title'      => 'Test Show',
            'show_date'  => '2026-07-01',
            'status'     => 'pending_review',
            'created_by' => 1,
        ], $overrides));
    }

    private function shipmentRow(array $overrides = []): array
    {
        return array_merge([
            'order_id'                => '1199202642',
            'buyer'                   => 'somebuyer',
            'item_name'               => null,
            'weight_oz'               => 4.0,
            'box_length_in'           => 12.0,
            'box_width_in'            => 12.0,
            'box_height_in'           => 12.0,
            'shipping_carrier'        => 'USPS',
            'shipping_service'        => 'Ground Advantage',
            'shipping_status_scraped' => 'pending',
        ], $overrides);
    }

    // ── persistShowOrders() merge path ──────────────────────────────────────

    public function test_merges_shipment_fields_onto_existing_order_matched_by_order_id(): void
    {
        $show  = $this->makeShow();
        $order = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => '1199202642',
            'item_name' => 'EVENT! #130', 'quantity' => 1,
            'total_price' => 13.00, 'total_cost' => 0, 'show_date' => $show->show_date,
        ]);

        $result = app(WhatnotScraper::class)->persistShowOrders($show, [$this->shipmentRow()]);

        $this->assertEquals(['created' => 0, 'skipped' => 0, 'updated' => 1], $result);

        $order->refresh();
        $this->assertEquals(4.0, (float) $order->shipment_weight_oz);
        $this->assertEquals(12.0, (float) $order->box_length_in);
        $this->assertEquals('USPS', $order->shipping_carrier);
        $this->assertEquals('Ground Advantage', $order->shipping_service);
        $this->assertEquals('pending', $order->shipping_status);
        $this->assertNotNull($order->shipment_synced_at);
    }

    public function test_does_not_create_a_new_order_when_shipment_row_has_no_matching_order_id(): void
    {
        $show = $this->makeShow();

        $result = app(WhatnotScraper::class)->persistShowOrders($show, [
            $this->shipmentRow(['order_id' => '999999999', 'buyer' => 'nobody', 'item_name' => null]),
        ]);

        // No existing order carries this order_id, and buyer/item_name alone
        // (with no real sale data) still creates a stub order the normal way —
        // that's expected orders-tab behaviour, not a shipments-merge concern.
        $this->assertEquals(1, $result['created']);
    }

    public function test_shipping_status_never_regresses_backward(): void
    {
        $show  = $this->makeShow();
        $order = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => '111',
            'quantity' => 1, 'total_price' => 10, 'total_cost' => 0,
            'show_date' => $show->show_date, 'shipping_status' => 'shipped',
        ]);

        // A stale/cached shipments-page scrape claims "pending" — must not
        // overwrite the already-more-advanced "shipped" status.
        app(WhatnotScraper::class)->persistShowOrders($show, [
            $this->shipmentRow(['order_id' => '111', 'shipping_status_scraped' => 'pending']),
        ]);

        $this->assertEquals('shipped', $order->refresh()->shipping_status);
        // Non-status fields still merge even when the status itself is guarded.
        $this->assertEquals(4.0, (float) $order->shipment_weight_oz);
    }

    public function test_shipping_status_advances_forward(): void
    {
        $show  = $this->makeShow();
        $order = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => '222',
            'quantity' => 1, 'total_price' => 10, 'total_cost' => 0,
            'show_date' => $show->show_date, 'shipping_status' => 'pending',
        ]);

        app(WhatnotScraper::class)->persistShowOrders($show, [
            $this->shipmentRow(['order_id' => '222', 'shipping_status_scraped' => 'delivered']),
        ]);

        $this->assertEquals('delivered', $order->refresh()->shipping_status);
    }

    public function test_row_with_no_shipment_data_is_skipped_not_updated(): void
    {
        $show  = $this->makeShow();
        $order = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => '333',
            'quantity' => 1, 'total_price' => 10, 'total_cost' => 0,
            'show_date' => $show->show_date,
        ]);

        $result = app(WhatnotScraper::class)->persistShowOrders($show, [
            ['order_id' => '333', 'buyer' => 'x'],
        ]);

        $this->assertEquals(['created' => 0, 'skipped' => 1, 'updated' => 0], $result);
        $this->assertNull($order->refresh()->shipment_synced_at);
    }

    // ── WhatnotSyncEngine::syncShipmentUpdatesForChannel() ───────────────────

    public function test_sync_engine_selects_only_shows_with_open_shipments(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);

        $openShow = $this->makeShow([
            'whatnot_channel_id' => $channel->id,
            'detail_url' => 'https://www.whatnot.com/live/x/11111111-1111-1111-1111-111111111111',
        ]);
        WhatnotShowOrder::create([
            'show_id' => $openShow->id, 'whatnot_order_id' => 'a',
            'quantity' => 1, 'total_price' => 10, 'total_cost' => 0,
            'show_date' => $openShow->show_date, 'shipping_status' => 'pending',
        ]);

        $doneShow = $this->makeShow([
            'whatnot_channel_id' => $channel->id,
            'detail_url' => 'https://www.whatnot.com/live/x/22222222-2222-2222-2222-222222222222',
        ]);
        WhatnotShowOrder::create([
            'show_id' => $doneShow->id, 'whatnot_order_id' => 'b',
            'quantity' => 1, 'total_price' => 10, 'total_cost' => 0,
            'show_date' => $doneShow->show_date, 'shipping_status' => 'delivered',
        ]);

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->expects('refreshShipmentsForShows')
            ->once()
            ->withArgs(function ($shows) use ($openShow) {
                return $shows->count() === 1 && $shows->first()->id === $openShow->id;
            })
            ->andReturn(['updated' => 2, 'skipped_shows' => 0]);

        $engine = new WhatnotSyncEngine($scraper);
        $result = $engine->syncShipmentUpdatesForChannel($channel);

        $this->assertEquals(2, $result['updated']);
        $this->assertEquals(1, $result['shows_checked']);
    }

    public function test_sync_engine_no_ops_when_nothing_needs_refreshing(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldNotReceive('refreshShipmentsForShows');

        $engine = new WhatnotSyncEngine($scraper);
        $result = $engine->syncShipmentUpdatesForChannel($channel);

        $this->assertEquals(['updated' => 0, 'skipped_shows' => 0, 'shows_checked' => 0], $result);
    }

    // ── SyncWhatnotShipmentsJob heartbeat ─────────────────────────────────────

    public function test_job_records_a_last_sync_heartbeat_for_the_dashboard(): void
    {
        $channel = WhatnotChannel::create([
            'name' => 'Chan', 'status' => 'active', 'include_in_import' => true,
        ]);

        $engine = Mockery::mock(WhatnotSyncEngine::class);
        $engine->expects('syncShipmentUpdatesForChannel')
            ->once()
            ->andReturn(['updated' => 3, 'skipped_shows' => 0, 'shows_checked' => 5]);
        $this->app->instance(WhatnotSyncEngine::class, $engine);

        $this->assertNull(Setting::get('whatnot_last_shipment_sync_at'));

        (new SyncWhatnotShipmentsJob())->handle($engine);

        $this->assertNotNull(Setting::get('whatnot_last_shipment_sync_at'));
        $summary = json_decode(Setting::get('whatnot_last_shipment_sync_summary'), true);
        $this->assertEquals(3, $summary['updated']);
        $this->assertEquals(5, $summary['shows_checked']);
        $this->assertEquals([], $summary['errors']);
    }
}
