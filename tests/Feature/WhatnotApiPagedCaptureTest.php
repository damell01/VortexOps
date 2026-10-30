<?php

namespace Tests\Feature;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Services\WhatnotApiPagedCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatnotApiPagedCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): WhatnotApiConnection
    {
        $channel = WhatnotChannel::create(['name' => 'Paged Test', 'whatnot_username' => 'paged-test']);
        return WhatnotApiConnection::create([
            'whatnot_channel_id' => $channel->id,
            'access_token' => 'test-token',
            'enabled' => false,
            'sync_enabled' => false,
        ]);
    }

    private function capture(WhatnotApiConnection $connection, int $maxPages = 10): array
    {
        return app(WhatnotApiPagedCapture::class)->capture(
            $connection,
            'orders',
            app(\App\Services\WhatnotApiReadOnlySync::class),
            app(\App\Services\WhatnotSellerApiClient::class),
            'meta.next_cursor',
            'cursor',
            $maxPages
        );
    }

    public function test_gate_prevents_requests_without_verified_contract(): void
    {
        $connection = $this->connection();
        Http::fake();
        $this->expectException(RuntimeException::class);
        try {
            $this->capture($connection);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_two_pages_complete_without_modifying_ledger(): void
    {
        config(['services.whatnot_seller.pagination_verified' => true]);
        $connection = $this->connection();
        Http::fakeSequence()
            ->push(['data' => [['id' => 'first']], 'meta' => ['next_cursor' => 'page-2']], 200)
            ->push(['data' => [['id' => 'second']], 'meta' => ['next_cursor' => null]], 200);
        $result = $this->capture($connection);
        $this->assertSame(['ok' => true, 'pages' => 2, 'new_snapshots' => 2, 'complete' => true], $result);
        $this->assertSame(2, DB::table('whatnot_api_snapshots')->count());
        $this->assertSame(0, DB::table('whatnot_ledger_entries')->count());
        Http::assertSentCount(2);
    }

    public function test_page_limit_reports_incomplete_capture(): void
    {
        config(['services.whatnot_seller.pagination_verified' => true]);
        Http::fake(['*' => Http::response(['meta' => ['next_cursor' => 'more']], 200)]);
        $result = $this->capture($this->connection(), 1);
        $this->assertFalse($result['complete']);
        $this->assertSame(1, $result['pages']);
        $this->assertSame(1, $result['new_snapshots']);
    }
}
