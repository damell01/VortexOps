<?php

namespace Tests\Feature;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Services\WhatnotApiReadOnlySync;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatnotApiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): WhatnotApiConnection
    {
        $channel = WhatnotChannel::create(['name' => 'Test Seller', 'whatnot_username' => 'test-seller']);
        return WhatnotApiConnection::create([
            'whatnot_channel_id' => $channel->id,
            'access_token' => 'fixture-token-not-a-real-key',
            'enabled' => false,
            'sync_enabled' => false,
        ]);
    }

    public function test_tokens_are_encrypted_and_hidden(): void
    {
        $connection = $this->connection();
        $this->assertNotSame('fixture-token-not-a-real-key', $connection->getRawOriginal('access_token'));
        $this->assertSame('fixture-token-not-a-real-key', $connection->fresh()->access_token);
        $this->assertArrayNotHasKey('access_token', $connection->toArray());
    }

    public function test_capture_is_idempotent_and_does_not_touch_financial_tables(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::response(['data' => [['id' => 'fixture-show']]], 200)]);
        $service = app(WhatnotApiReadOnlySync::class);
        $client = app(WhatnotSellerApiClient::class);
        $this->assertTrue($service->capture($connection, 'shows', $client)['ok']);
        $this->assertTrue($service->capture($connection, 'shows', $client)['ok']);
        $this->assertSame(1, DB::table('whatnot_api_snapshots')->count());
        $stored = DB::table('whatnot_api_snapshots')->first()->payload;
        $this->assertStringNotContainsString('fixture-show', $stored);
        $this->assertStringContainsString('fixture-show', \Illuminate\Support\Facades\Crypt::decryptString($stored));
        $this->assertSame(2, DB::table('whatnot_api_sync_audits')->count());
        $this->assertSame(0, DB::table('whatnot_ledger_entries')->count());
        $this->assertSame(0, DB::table('shows')->count());
    }

    public function test_failed_response_is_not_saved(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);
        $result = app(WhatnotApiReadOnlySync::class)->capture($connection, 'orders', app(WhatnotSellerApiClient::class));
        $this->assertFalse($result['ok']);
        $this->assertSame(0, DB::table('whatnot_api_snapshots')->count());
    }

    public function test_unsupported_resource_is_rejected_before_network_call(): void
    {
        $this->expectException(RuntimeException::class);
        app(WhatnotApiReadOnlySync::class)->capture($this->connection(), 'payouts', app(WhatnotSellerApiClient::class));
    }
}
