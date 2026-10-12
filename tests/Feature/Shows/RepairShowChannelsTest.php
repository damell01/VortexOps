<?php

namespace Tests\Feature\Shows;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vortex Shop streams that the old import folded into same-named Vortex
 * Breaks shows are split back out, with their own figures and orders.
 */
class RepairShowChannelsTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $breaks;
    private WhatnotChannel $shop;
    private const BREAKS_ID = '11111111-1111-4111-8111-111111111111';
    private const SHOP_ID = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $this->shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'whatnot_username' => 'vortexshop', 'status' => 'active']);
    }

    private function url(string $id): string
    {
        return "https://www.whatnot.com/dashboard/live/{$id}";
    }

    private function log(Show $show, WhatnotChannel $channel, string $liveId, array $row): void
    {
        ShowIngestionLog::create(['show_id' => $show->id, 'whatnot_channel_id' => $channel->id, 'source' => 'whatnot', 'status' => 'success',
            'raw_payload' => $row + ['detail_url' => $this->url($liveId), '_channel_id' => $channel->id]]);
    }

    /** What the old import left behind: the Shop stream's figures, link and orders on the Breaks show. */
    private function mergedShow(string $status = 'draft'): Show
    {
        $show = Show::create(['whatnot_channel_id' => $this->breaks->id, 'title' => 'Sunday Night Rips', 'show_date' => '2026-10-11',
            'status' => $status, 'import_source' => 'auto_whatnot', 'gross_revenue' => 200, 'units_sold' => 4,
            'detail_url' => $this->url(self::SHOP_ID), 'channel_attribution_suspect' => true]);
        $this->log($show, $this->breaks, self::BREAKS_ID, ['title' => 'Sunday Night Rips', 'show_date' => '2026-10-11', 'gross_revenue' => 500, 'units_sold' => 10]);
        $this->log($show, $this->shop, self::SHOP_ID, ['title' => 'Sunday Night Rips', 'show_date' => '2026-10-11', 'gross_revenue' => 200, 'units_sold' => 4]);
        foreach ([[self::BREAKS_ID, 'B-1'], [self::BREAKS_ID, 'B-2'], [self::SHOP_ID, 'S-1']] as [$id, $order]) {
            WhatnotShowOrder::create(['show_id' => $show->id, 'whatnot_order_id' => $order, 'whatnot_show_url' => $this->url($id), 'buyer_username' => 'buyer', 'item_name' => 'Pack', 'quantity' => 1, 'status' => 'completed']);
        }
        Shipment::create(['show_id' => $show->id, 'whatnot_order_id' => WhatnotShowOrder::where('whatnot_order_id', 'S-1')->value('id'), 'buyer_username' => 'buyer']);

        return $show;
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->mergedShow();
        $this->artisan('whatnot:repair-channels')->expectsOutputToContain('Split out Vortex Shop stream')->expectsOutputToContain('--apply')->assertSuccessful();
        $this->assertSame(1, Show::count());
    }

    public function test_a_merged_shop_stream_is_split_out_with_its_own_figures_and_orders(): void
    {
        $show = $this->mergedShow();
        $this->artisan('whatnot:repair-channels --apply')->assertSuccessful();

        $this->assertSame(2, Show::count());
        $shopShow = Show::where('whatnot_channel_id', $this->shop->id)->sole();
        $this->assertSame(self::SHOP_ID, $shopShow->whatnot_show_id);
        $this->assertEquals(200, (float) $shopShow->gross_revenue);
        $this->assertSame(['S-1'], $shopShow->orders()->pluck('whatnot_order_id')->all());
        $this->assertSame($shopShow->id, Shipment::sole()->show_id);

        $show->refresh();
        $this->assertSame($this->breaks->id, $show->whatnot_channel_id);
        $this->assertEquals(500, (float) $show->gross_revenue, 'its own figures are back');
        $this->assertSame(self::BREAKS_ID, $show->whatnot_show_id);
        $this->assertSame(['B-1', 'B-2'], $show->orders()->orderBy('whatnot_order_id')->pluck('whatnot_order_id')->all());
        $this->assertFalse((bool) $show->channel_attribution_suspect);

        // Running it again finds nothing left to do.
        $this->artisan('whatnot:repair-channels')->expectsOutputToContain('Nothing to repair')->assertSuccessful();
    }

    public function test_an_approved_show_keeps_its_figures_and_is_noted_for_checking(): void
    {
        $show = $this->mergedShow('reconciled');
        $this->artisan('whatnot:repair-channels --apply')->assertSuccessful();

        $this->assertSame(2, Show::count());
        $show->refresh();
        $this->assertEquals(200, (float) $show->gross_revenue, 'approved figures untouched');
        $this->assertStringContainsString('Channel repair', (string) $show->revision_notes);
        $this->assertTrue((bool) $show->channel_attribution_suspect, 'stays on the review list');
    }

    public function test_a_show_on_the_wrong_channel_is_moved_and_an_ambiguous_one_is_left_for_a_person(): void
    {
        $wrong = Show::create(['whatnot_channel_id' => $this->breaks->id, 'whatnot_show_id' => '33333333-3333-4333-8333-333333333333', 'title' => 'Shop restock',
            'show_date' => '2026-10-12', 'status' => 'draft', 'channel_attribution_suspect' => true,
            'raw_import_payload' => ['_seller_hub_channels' => [$this->shop->id]]]);
        $both = Show::create(['whatnot_channel_id' => $this->breaks->id, 'whatnot_show_id' => '44444444-4444-4444-8444-444444444444', 'title' => 'Mystery show',
            'show_date' => '2026-10-12', 'status' => 'draft', 'channel_attribution_suspect' => true,
            'raw_import_payload' => ['_seller_hub_channels' => [$this->shop->id, $this->breaks->id]]]);

        $this->artisan('whatnot:repair-channels --apply')->expectsOutputToContain('Needs a person')->assertSuccessful();

        $this->assertSame($this->shop->id, $wrong->fresh()->whatnot_channel_id);
        $this->assertFalse((bool) $wrong->fresh()->channel_attribution_suspect);
        $this->assertSame($this->breaks->id, $both->fresh()->whatnot_channel_id);
        $this->assertTrue((bool) $both->fresh()->channel_attribution_suspect);
    }

    public function test_a_merged_show_that_took_the_shop_streams_id_keeps_its_own_channel_and_report(): void
    {
        // Nightly analytics saved the live id from the overwritten link.
        $show = $this->mergedShow();
        $show->forceFill(['whatnot_show_id' => self::SHOP_ID])->save();

        $this->artisan('whatnot:repair-channels --apply')->assertSuccessful();

        $show->refresh();
        $this->assertSame($this->breaks->id, $show->whatnot_channel_id, 'the record stays on Breaks with its report and pay');
        $this->assertSame(self::BREAKS_ID, $show->whatnot_show_id);
        $this->assertEquals(500, (float) $show->gross_revenue);
        $shopShow = Show::where('whatnot_channel_id', $this->shop->id)->sole();
        $this->assertSame(self::SHOP_ID, $shopShow->whatnot_show_id);
        $this->assertSame(['S-1'], $shopShow->orders()->pluck('whatnot_order_id')->all());
    }

    public function test_a_second_copy_made_by_the_old_backfill_is_folded_into_the_original(): void
    {
        // The hourly show: wrong (UTC) date, live id only in its link, with the streamer's report.
        $original = Show::create(['whatnot_channel_id' => $this->breaks->id, 'title' => 'Late night rips', 'show_date' => '2026-10-11',
            'start_time' => '02:00:00', 'status' => 'mapping', 'import_source' => 'auto_whatnot', 'detail_url' => $this->url(self::BREAKS_ID)]);
        // The copy the old discovery added beside it, with the right date.
        $copy = Show::create(['whatnot_channel_id' => $this->breaks->id, 'whatnot_show_id' => self::BREAKS_ID, 'title' => 'Late night rips',
            'show_date' => '2026-10-10', 'start_time' => '21:00:00', 'status' => 'draft', 'import_source' => 'auto_whatnot',
            'raw_import_payload' => ['_seller_hub_channels' => [$this->breaks->id]]]);

        $this->artisan('whatnot:repair-channels')->expectsOutputToContain('Fold copy in')->assertSuccessful();
        $this->assertSame(2, Show::count(), 'dry run changes nothing');

        $this->artisan('whatnot:repair-channels --apply')->assertSuccessful();

        $this->assertNull(Show::find($copy->id));
        $original->refresh();
        $this->assertSame('2026-10-10', $original->show_date->toDateString());
        $this->assertSame('21:00:00', $original->start_time->format('H:i:s'));
        $this->assertSame(self::BREAKS_ID, $original->whatnot_show_id);
        $this->assertSame('mapping', $original->status, 'the original keeps its own progress');
    }

    public function test_moves_only_leaves_splits_alone_and_show_limits_to_given_ids(): void
    {
        $merged = $this->mergedShow();
        $wrong = Show::create(['whatnot_channel_id' => $this->breaks->id, 'whatnot_show_id' => '33333333-3333-4333-8333-333333333333', 'title' => 'Shop restock',
            'show_date' => '2026-10-12', 'status' => 'draft', 'raw_import_payload' => ['_seller_hub_channels' => [$this->shop->id]], 'channel_attribution_suspect' => true]);

        $this->artisan('whatnot:repair-channels --apply --moves-only')->assertSuccessful();
        $this->assertSame($this->shop->id, $wrong->fresh()->whatnot_channel_id);
        $this->assertSame(2, Show::count(), 'the merged show was not split');

        $this->artisan('whatnot:repair-channels --apply --show='.$merged->id)->assertSuccessful();
        $this->assertSame(3, Show::count());
    }
}
