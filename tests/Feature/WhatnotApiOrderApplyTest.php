<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotApiOrderApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Seller API orders reach whatnot_show_orders — the table reports, shipments
 * and show pages read — only by exact show ID, without double counting the
 * scraper's rows, and only when switched on.
 */
class WhatnotApiOrderApplyTest extends TestCase
{
    use RefreshDatabase;

    private const SHOW_UUID = '1b4e28ba-2fa1-11d2-883f-0016d3cca427';

    private WhatnotChannel $channel;
    private Show $show;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = WhatnotChannel::create(['name' => 'Vortex', 'whatnot_username' => 'vortex']);
        $this->show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'whatnot_show_id' => self::SHOW_UUID,
            'title' => 'Friday Breaks',
            'show_date' => '2026-11-01',
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function allowWrites(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('services.whatnot_seller.live_requests_enabled', true);
        config()->set('services.whatnot_seller.contract_verified', true);
        config()->set('services.whatnot_seller.apply_enabled', true);
    }

    private function order(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'show_id' => self::SHOW_UUID,
            'status' => 'completed',
            'transaction_type' => 'auction',
            'created_at' => '2026-11-01T20:15:00Z',
            'amounts' => [
                'subtotal' => ['amount' => '25.00', 'currency' => 'USD'],
                'shipping' => ['amount' => '4.50', 'currency' => 'USD'],
                'tax' => ['amount' => '2.00', 'currency' => 'USD'],
                'total' => ['amount' => '31.50', 'currency' => 'USD'],
            ],
        ], $overrides);
    }

    private function snapshot(array $orders): int
    {
        $json = json_encode(['data' => $orders], JSON_UNESCAPED_SLASHES);

        return (int) DB::table('whatnot_api_snapshots')->insertGetId([
            'whatnot_channel_id' => $this->channel->id,
            'resource' => 'orders',
            'payload_sha256' => hash('sha256', $json),
            'payload' => Crypt::encryptString($json),
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function apply(int $snapshot, bool $dryRun = false): array
    {
        return app(WhatnotApiOrderApplier::class)->apply($this->channel->id, $snapshot, $dryRun);
    }

    public function test_a_preview_counts_what_would_change_and_writes_nothing(): void
    {
        $result = $this->apply($this->snapshot([$this->order('A1'), $this->order('A2')]), dryRun: true);

        $this->assertSame(2, $result['counts']['created']);
        $this->assertSame(0, WhatnotShowOrder::count());
        $this->assertDatabaseHas('whatnot_api_sync_audits', ['resource' => 'orders_apply', 'status' => 'dry_run']);
    }

    public function test_writing_is_refused_until_every_switch_is_on(): void
    {
        $snapshot = $this->snapshot([$this->order('A1')]);

        $this->expectException(RuntimeException::class);
        try {
            $this->apply($snapshot);
        } finally {
            $this->assertSame(0, WhatnotShowOrder::count());
        }
    }

    public function test_orders_land_on_the_show_with_the_documented_fields(): void
    {
        $this->allowWrites();
        $this->apply($this->snapshot([$this->order('A1')]));

        $row = WhatnotShowOrder::sole();
        $this->assertSame($this->show->id, $row->show_id);
        $this->assertSame('A1', $row->whatnot_order_id);
        $this->assertSame('completed', $row->status);
        $this->assertSame('25.00', (string) $row->total_price);
        $this->assertSame('4.50', (string) $row->shipping_amount);
        $this->assertSame('2.00', (string) $row->tax_amount);
        $this->assertSame('seller_api', $row->raw_data['_source']);
        $this->assertNotNull($row->ordered_at_whatnot);
    }

    public function test_reapplying_is_idempotent_and_updates_only_api_fields(): void
    {
        $this->allowWrites();
        $this->apply($this->snapshot([$this->order('A1')]));
        WhatnotShowOrder::sole()->update(['item_name' => 'Mapped by hand']);

        $again = $this->apply($this->snapshot([$this->order('A1', ['updated_at' => 'x'])]));
        $this->assertSame(1, $again['counts']['unchanged']);

        $later = $this->apply($this->snapshot([$this->order('A1', ['status' => 'cancelled'])]));
        $this->assertSame(1, $later['counts']['updated']);
        $row = WhatnotShowOrder::sole();
        $this->assertSame('cancelled', $row->status);
        $this->assertSame('Mapped by hand', $row->item_name);
    }

    public function test_cancelled_orders_without_a_show_or_show_match_are_not_created(): void
    {
        $this->allowWrites();
        $result = $this->apply($this->snapshot([
            $this->order('C1', ['status' => 'cancelled']),
            $this->order('F1', ['status' => 'failed']),
            $this->order('M1', ['show_id' => null]),
            $this->order('U1', ['show_id' => '9b4e28ba-2fa1-11d2-883f-0016d3cca427']),
            $this->order('E1', ['amounts' => ['subtotal' => ['amount' => '25.00', 'currency' => 'EUR']]]),
        ]));

        $this->assertSame(2, $result['counts']['not_a_sale']);
        $this->assertSame(1, $result['counts']['no_show_id']);
        $this->assertSame(1, $result['counts']['show_not_found']);
        $this->assertSame(1, $result['counts']['invalid']);
        $this->assertSame(0, WhatnotShowOrder::count());
    }

    public function test_a_show_whose_scraper_order_ids_do_not_match_is_skipped_not_doubled(): void
    {
        $this->allowWrites();
        WhatnotShowOrder::create(['show_id' => $this->show->id, 'whatnot_order_id' => 'SELLERHUB-1', 'item_name' => 'Box', 'quantity' => 1, 'total_price' => 25]);

        $result = $this->apply($this->snapshot([$this->order('API-1'), $this->order('API-2')]));

        $this->assertSame(2, $result['counts']['id_format_mismatch']);
        $this->assertArrayHasKey($this->show->id, $result['skipped_shows']);
        $this->assertSame(1, WhatnotShowOrder::count());
    }

    public function test_matching_scraper_order_ids_are_updated_in_place(): void
    {
        $this->allowWrites();
        WhatnotShowOrder::create(['show_id' => $this->show->id, 'whatnot_order_id' => 'A1', 'item_name' => 'Box', 'quantity' => 1, 'total_price' => 20, 'status' => 'pending']);

        $result = $this->apply($this->snapshot([$this->order('A1'), $this->order('A2')]));

        $this->assertSame(1, $result['counts']['updated']);
        // A show with scraper sales never gets extra API sales until it is fully
        // reconciled, so one sale can't be counted twice under two ID formats.
        $this->assertSame(0, $result['counts']['created']);
        $this->assertSame(1, $result['counts']['id_format_mismatch']);
        $this->assertSame(1, WhatnotShowOrder::count());
        $updated = WhatnotShowOrder::where('whatnot_order_id', 'A1')->sole();
        $this->assertSame('completed', $updated->status);
        $this->assertSame('Box', $updated->item_name);
    }

    public function test_an_order_filed_under_another_show_is_never_moved(): void
    {
        $this->allowWrites();
        $other = Show::create(['whatnot_channel_id' => $this->channel->id, 'title' => 'Other', 'show_date' => '2026-10-30', 'created_by' => $this->show->created_by]);
        WhatnotShowOrder::create(['show_id' => $other->id, 'whatnot_order_id' => 'A1', 'item_name' => 'Box', 'quantity' => 1]);

        $result = $this->apply($this->snapshot([$this->order('A1')]));

        $this->assertSame(1, $result['counts']['other_show']);
        $this->assertSame($other->id, WhatnotShowOrder::sole()->show_id);
    }

    public function test_the_preview_command_reports_without_writing(): void
    {
        $this->snapshot([$this->order('A1')]);

        $this->artisan('whatnot:api-apply-orders', ['--channel' => $this->channel->id])
            ->expectsOutputToContain('Preview (nothing written)')
            ->assertSuccessful();

        $this->assertSame(0, WhatnotShowOrder::count());
    }
}
