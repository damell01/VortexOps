<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Services\FulfillmentShipmentRefreshService;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Refreshing one show's shipments stops at the show's date and never fails just because the feed is long. */
class ShipmentRefreshStopsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.whatnot.com/seller-api/rest/v2';

    private Show $show;

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn () => 'production');
        config()->set('services.whatnot_seller.live_requests_enabled', true);
        config()->set('services.whatnot_seller.contract_verified', true);
        config()->set('services.whatnot_seller.apply_enabled', true);
        config()->set('services.whatnot_seller.base_url', self::BASE);
        $channel = WhatnotChannel::create(['name' => 'Vortex', 'whatnot_username' => 'vortex']);
        $this->show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Friday Breaks', 'show_date' => now()->subDays(3)->toDateString(), 'created_by' => User::factory()->create()->id]);
        WhatnotShowOrder::create(['show_id' => $this->show->id, 'whatnot_order_id' => 'ORD-1', 'buyer_username' => 'cardfan', 'item_name' => 'Box', 'quantity' => 1, 'status' => 'completed']);
        WhatnotApiConnection::create(['whatnot_channel_id' => $channel->id, 'access_token' => 'wn_pat_tk_fixture', 'enabled' => true, 'sync_enabled' => true]);
        $this->app->instance(WhatnotSellerApiClient::class, new WhatnotSellerApiClient(fn () => null));
    }

    private function shipment(string $id, int $daysAgo): array
    {
        return ['id' => $id, 'status' => 'in_transit', 'created_at' => now()->subDays($daysAgo)->toIso8601ZuluString(), 'tracking' => null, 'documents' => null];
    }

    public function test_it_stops_at_the_page_that_reaches_back_before_the_show(): void
    {
        $requested = [];
        Http::fake(function (Request $request) use (&$requested) {
            $path = str_replace(self::BASE, '', strtok($request->url(), '?'));
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $requested[] = $path . (isset($q['cursor']) ? '@' . $q['cursor'] : '');
            return match (true) {
                $path === '/shipments' && ! isset($q['cursor']) => Http::response(['data' => [$this->shipment('new-1', 1)], 'next_cursor' => 'p2']),
                $path === '/shipments' && $q['cursor'] === 'p2' => Http::response(['data' => [$this->shipment('mid-1', 2), $this->shipment('old-1', 30)], 'next_cursor' => 'p3']),
                str_ends_with($path, '/items') => Http::response(['data' => [['order_id' => 'SOMEONE-ELSE']], 'next_cursor' => null]),
                default => Http::response(['data' => [], 'next_cursor' => null]),
            };
        });

        $result = app(FulfillmentShipmentRefreshService::class)->refresh($this->show);

        $this->assertSame(2, $result['checked']);               // new-1 and mid-1, not the 30-day-old one
        $this->assertFalse($result['incomplete']);
        $this->assertNotContains('/shipments@p3', $requested);   // stopped at the page that went back past the show
        $this->assertNotContains('/shipments/old-1/items', $requested);
    }

    public function test_an_endless_feed_is_a_partial_refresh_not_an_error(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace(self::BASE, '', strtok($request->url(), '?'));
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $n = (int) ($q['cursor'] ?? 0) + 1;
            return str_ends_with($path, '/items')
                ? Http::response(['data' => [], 'next_cursor' => null])
                : Http::response(['data' => [$this->shipment('s' . $n, 1)], 'next_cursor' => (string) $n]);
        });

        $result = app(FulfillmentShipmentRefreshService::class)->refresh($this->show);

        $this->assertTrue($result['incomplete']);
        $this->assertSame(20, $result['pages']);
    }
}
