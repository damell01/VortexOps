<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotApiLedgerImporter;
use App\Services\WhatnotApiShowAnalyticsApplier;
use App\Services\WhatnotApiSync;
use App\Services\WhatnotApiVerifier;
use App\Services\WhatnotApiHealth;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Seller API sync, against responses shaped like the official schemas
 * (developers.whatnot.com/api-reference/openapi.json, reports CSV columns).
 */
class WhatnotApiSyncTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.whatnot.com/seller-api/rest/v2';
    private const SHOW_UUID = '086df56b-c77d-436d-bff5-5ac154016aac';
    private const REPORT_URL = 'https://reports.example-presigned.test/financial.csv';

    private WhatnotChannel $channel;
    private Show $show;
    private WhatnotApiConnection $connection;
    private array $waits = [];

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn () => 'production');
        config()->set('services.whatnot_seller.live_requests_enabled', true);
        config()->set('services.whatnot_seller.contract_verified', true);
        config()->set('services.whatnot_seller.apply_enabled', true);
        config()->set('services.whatnot_seller.base_url', self::BASE);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex', 'whatnot_username' => 'vortex']);
        $this->show = Show::create([
            'whatnot_channel_id' => $this->channel->id, 'whatnot_show_id' => self::SHOW_UUID,
            'title' => 'Friday Breaks', 'show_date' => now()->subDay()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);
        $this->connection = WhatnotApiConnection::create([
            'whatnot_channel_id' => $this->channel->id, 'access_token' => 'wn_pat_tk_fixture',
            'enabled' => true, 'sync_enabled' => true,
        ]);
        $sleeper = function (float $s) { $this->waits[] = $s; };
        $this->app->instance(WhatnotSellerApiClient::class, new WhatnotSellerApiClient($sleeper));
    }

    private function money(string $amount): array
    {
        return ['amount' => $amount, 'currency' => 'USD'];
    }

    private function order(string $id, string $status = 'completed'): array
    {
        return [
            'id' => $id, 'url' => 'https://www.whatnot.com/dashboard/orders/'.$id, 'status' => $status,
            'show_id' => self::SHOW_UUID, 'transaction_type' => 'auction',
            'buyer' => ['id' => '9', 'username' => 'cardfan', 'display_name' => 'Card Fan'],
            'amounts' => ['subtotal' => $this->money('25'), 'shipping' => $this->money('4.50'), 'tax' => $this->money('2.00'), 'applied_discount' => null, 'total' => $this->money('31.50')],
            'created_at' => now()->subHours(20)->toIso8601ZuluString(), 'updated_at' => now()->subHours(20)->toIso8601ZuluString(), 'cancelled_at' => null,
        ];
    }

    private function analytics(string $status = 'GENERATED'): array
    {
        return [
            'show_id' => self::SHOW_UUID, 'title' => 'Friday Breaks', 'start_time' => now()->subDay()->toIso8601ZuluString(),
            'calculation_status' => $status, 'sales' => $this->money('1250.00'), 'estimated_earnings' => $this->money('1010.40'),
            'orders_count' => 40, 'average_order_value' => $this->money('31.25'), 'giveaway_orders_count' => 6,
            'giveaway_spend' => $this->money('-18.00'), 'show_impression_count' => 900, 'show_clickthrough_rate' => 4.5,
            'buyers_count' => 22, 'first_time_buyers_count' => 7, 'returning_buyers_count' => 15, 'giveaway_winners_count' => 6,
            'first_time_giveaway_winners_count' => 2, 'returning_giveaway_winners_count' => 4, 'buyer_shares_count' => 3,
            'stream_minutes' => 185, 'max_concurrent_viewers' => 61, 'total_views' => 410, 'distinct_viewers_count' => 300, 'average_rating' => 4.9,
        ];
    }

    private function financialCsv(array $rows): string
    {
        $cols = ['transaction_completed_at_utc', 'order_placed_at_utc', 'seller_id', 'transaction_type', 'transaction_message', 'order_id', 'livestream_id', 'buyer_name', 'buyer_state', 'transaction_currency', 'transaction_amount', 'ledger_transaction_id'];
        $lines = [implode(',', $cols)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', (string) ($row[$c] ?? '')).'"', $cols));
        }
        return implode("\n", $lines)."\n";
    }

    private function fakeWhatnot(array $orders, string $csv, string $analyticsStatus = 'GENERATED'): void
    {
        Http::fake(function (Request $request) use ($orders, $csv, $analyticsStatus) {
            $path = str_replace(self::BASE, '', strtok($request->url(), '?'));
            return match (true) {
                $request->url() === self::REPORT_URL => Http::response($csv, 200),
                $path === '/users/me' => Http::response(['id' => '1', 'username' => 'vortexbreaks', 'display_name' => null], 200),
                $path === '/orders' => Http::response(['data' => $orders, 'next_cursor' => null], 200),
                str_starts_with($path, '/orders/') => Http::response(['data' => [[
                    'id' => 'it-1', 'order_id' => 'x', 'product_id' => '5', 'variant_id' => null, 'product_external_id' => null, 'variant_external_id' => null,
                    'purchased_item' => ['title' => '2025 Topps Chrome Hobby Box', 'description' => 'Sealed', 'sku' => 'TCH-25'],
                    'quantity' => 1, 'unit_price' => $this->money('25'), 'price_includes_tax' => false, 'shipment_id' => null,
                ]], 'next_cursor' => null], 200),
                $path === '/shows' => Http::response(['data' => [[
                    'id' => self::SHOW_UUID, 'title' => 'Friday Breaks', 'url' => 'https://www.whatnot.com/dashboard/lives/edit/'.self::SHOW_UUID,
                    'description' => null, 'status' => 'ended', 'visibility' => 'public', 'scheduled_start_at' => now()->subDay()->toIso8601ZuluString(),
                    'ended_at' => now()->subHours(20)->toIso8601ZuluString(), 'thumbnail' => null, 'created_at' => now()->subDays(3)->toIso8601ZuluString(), 'updated_at' => now()->toIso8601ZuluString(),
                ]], 'next_cursor' => null], 200),
                str_starts_with($path, '/analytics/shows/') => Http::response($this->analytics($analyticsStatus), 200),
                $path === '/reports' && $request->method() === 'POST' => Http::response(['id' => 'rep-1', 'report_type' => 'seller_financial_report', 'status' => 'pending', 'report_parameters' => null, 'report_url' => null, 'created_at' => now()->toIso8601ZuluString(), 'updated_at' => now()->toIso8601ZuluString()], 201),
                $path === '/reports' => Http::response(['data' => [], 'next_cursor' => null], 200),
                $path === '/reports/rep-1' => Http::response(['id' => 'rep-1', 'report_type' => 'seller_financial_report', 'status' => 'completed', 'report_parameters' => null, 'report_url' => self::REPORT_URL, 'created_at' => now()->toIso8601ZuluString(), 'updated_at' => now()->toIso8601ZuluString()], 200),
                default => Http::response(['type' => 'endpoint-not-found'], 404),
            };
        });
    }

    private function tipRow(string $id, string $amount, string $at): array
    {
        return ['transaction_completed_at_utc' => $at, 'transaction_type' => 'TIP', 'livestream_id' => self::SHOW_UUID, 'buyer_name' => 'cardfan', 'transaction_currency' => 'USD', 'transaction_amount' => $amount, 'ledger_transaction_id' => $id];
    }

    public function test_a_rate_limited_request_waits_retry_after_then_succeeds(): void
    {
        Http::fakeSequence(self::BASE.'/users/me*')
            ->push(['type' => 'rate-limited'], 429, ['Retry-After' => '2'])
            ->push(['id' => '1', 'username' => 'vortexbreaks'], 200);

        $response = app(WhatnotSellerApiClient::class)->call($this->connection, 'me');

        $this->assertTrue($response->successful());
        $this->assertGreaterThanOrEqual(2.0, $this->waits[0]);
    }

    public function test_a_full_sync_fills_orders_items_show_figures_and_ledger_tips(): void
    {
        config()->set('services.whatnot_seller.ledger_start', now()->subDays(10)->toDateString());
        $this->fakeWhatnot([$this->order('1485136575'), $this->order('1485136576', 'cancelled')], $this->financialCsv([
            $this->tipRow('L-1', '5.00', now()->subHours(20)->format('Y-m-d H:i:s')),
            ['transaction_completed_at_utc' => now()->subHours(19)->format('Y-m-d H:i:s'), 'transaction_type' => 'ORDER_EARNINGS', 'order_id' => '1485136575', 'livestream_id' => self::SHOW_UUID, 'transaction_currency' => 'USD', 'transaction_amount' => '21.30', 'ledger_transaction_id' => 'L-2', 'buyer_state' => 'TX'],
        ]));

        $result = app(WhatnotApiSync::class)->run($this->connection, days: 3, dryRun: false);

        $this->assertSame([], $result['errors']);
        $order = WhatnotShowOrder::sole();
        $this->assertSame('1485136575', $order->whatnot_order_id);
        $this->assertSame('2025 Topps Chrome Hobby Box', $order->item_name);
        $this->assertSame('cardfan', $order->buyer_username);
        $this->assertSame(self::SHOW_UUID, $order->raw_data['livestream_id']);

        $show = $this->show->fresh();
        $this->assertEquals(1250.00, (float) $show->gross_revenue);
        $this->assertEquals(1010.40, (float) $show->whatnot_net);
        $this->assertSame(185, (int) $show->show_duration);

        $tip = WhatnotLedgerEntry::where('dedup_key', 'wn_api:L-1')->sole();
        $this->assertSame('Tip', $tip->transaction_type);
        $this->assertSame(self::SHOW_UUID, $tip->raw_data['livestream_id']);
        $this->assertArrayNotHasKey('buyer_state', WhatnotLedgerEntry::where('dedup_key', 'wn_api:L-2')->sole()->raw_data);

        // Running again adds nothing.
        app(WhatnotApiSync::class)->run($this->connection, days: 3, dryRun: false);
        $this->assertSame(1, WhatnotShowOrder::count());
        $this->assertSame(2, WhatnotLedgerEntry::count());
    }

    public function test_a_preview_reads_everything_and_writes_nothing(): void
    {
        config()->set('services.whatnot_seller.ledger_start', now()->subDays(10)->toDateString());
        $this->fakeWhatnot([$this->order('1485136575')], $this->financialCsv([$this->tipRow('L-1', '5.00', now()->subHours(20)->format('Y-m-d H:i:s'))]));

        $result = app(WhatnotApiSync::class)->run($this->connection, days: 3, dryRun: true);

        $this->assertSame(1, $result['orders']['created']);
        $this->assertSame(1, $result['ledger']['created']);
        $this->assertSame(0, WhatnotShowOrder::count());
        $this->assertSame(0, WhatnotLedgerEntry::count());
        $this->assertNull($this->show->fresh()->gross_revenue);
    }

    public function test_ledger_rows_wait_for_the_chosen_start_date(): void
    {
        $csv = $this->financialCsv([$this->tipRow('L-1', '5.00', now()->subDay()->format('Y-m-d H:i:s'))]);

        $none = app(WhatnotApiLedgerImporter::class)->import($this->channel->id, $csv, dryRun: false);
        $this->assertSame(1, $none['counts']['before_start']);

        config()->set('services.whatnot_seller.ledger_start', now()->subDays(2)->toDateString());
        $later = app(WhatnotApiLedgerImporter::class)->import($this->channel->id, $csv, dryRun: false);
        $this->assertSame(1, $later['counts']['created']);
    }

    public function test_a_tip_the_scraper_already_has_is_linked_not_doubled(): void
    {
        config()->set('services.whatnot_seller.ledger_start', now()->subDays(2)->toDateString());
        $when = now()->subDay();
        $scraped = WhatnotLedgerEntry::create(['whatnot_channel_id' => $this->channel->id, 'created_date' => $when->copy()->setTimezone(config('app.timezone'))->addMinute()->toDateTimeString(),
            'amount' => 5.00, 'transaction_type' => 'Tip', 'dedup_key' => md5('scraped-tip'), 'raw_data' => []]);

        $result = app(WhatnotApiLedgerImporter::class)->import($this->channel->id,
            $this->financialCsv([$this->tipRow('L-1', '5.00', $when->copy()->utc()->format('Y-m-d H:i:s'))]), dryRun: false);

        $this->assertSame(1, $result['counts']['linked']);
        $this->assertSame(1, WhatnotLedgerEntry::count());
        $this->assertSame(self::SHOW_UUID, $scraped->fresh()->raw_data['livestream_id']);
    }

    public function test_analytics_not_ready_change_nothing_and_a_locked_show_is_flagged(): void
    {
        $applier = app(WhatnotApiShowAnalyticsApplier::class);
        $this->assertSame('not_ready', $applier->apply($this->channel->id, $this->analytics('PENDING'), dryRun: false)['outcome']);
        $this->assertNull($this->show->fresh()->gross_revenue);

        $this->show->forceFill(['status' => 'reconciled', 'gross_revenue' => 1200])->save();
        $this->assertSame('updated', $applier->apply($this->channel->id, $this->analytics(), dryRun: false)['outcome']);
        $show = $this->show->fresh();
        $this->assertEquals(1250.00, (float) $show->gross_revenue);
        $this->assertTrue((bool) $show->financials_revised_after_lock);
        $this->assertStringContainsString('Seller API', (string) $show->revision_notes);
    }

    public function test_test_connection_names_a_missing_permission(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace(self::BASE, '', strtok($request->url(), '?'));
            return match ($path) {
                '/users/me' => Http::response(['id' => '1', 'username' => 'vortexbreaks'], 200),
                '/reports' => Http::response(['type' => 'missing-scope', 'title' => 'Missing scope', 'status' => 403, 'required_scopes' => ['read:reports']], 403),
                default => Http::response(['data' => [], 'next_cursor' => null], 200),
            };
        });

        $result = app(WhatnotApiHealth::class)->check($this->connection);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('vortexbreaks', $result['message']);
        $this->assertStringContainsString('read:reports', $result['message']);
    }

    public function test_verification_reports_matching_numbers(): void
    {
        $this->show->forceFill(['gross_revenue' => 1250.00])->save();
        WhatnotShowOrder::create(['show_id' => $this->show->id, 'whatnot_order_id' => '1485136575', 'item_name' => 'Box', 'quantity' => 1]);
        WhatnotLedgerEntry::create(['whatnot_channel_id' => $this->channel->id, 'completed_date' => now()->subHours(20)->toDateTimeString(), 'amount' => 5.00, 'transaction_type' => 'Tip', 'dedup_key' => md5('t'), 'raw_data' => []]);
        $this->fakeWhatnot([$this->order('1485136575')], $this->financialCsv([$this->tipRow('L-1', '5.00', now()->subHours(20)->format('Y-m-d H:i:s'))]));

        $report = app(WhatnotApiVerifier::class)->verify($this->connection, 7);

        $this->assertSame([], $report['problems']);
        $this->assertSame(1, $report['shows'][0]['matching_order_ids']);
        $this->assertSame('ok', $report['shows'][0]['status']);
        $this->assertStringContainsString('Safe to turn on', $report['verdict']);
    }
}
