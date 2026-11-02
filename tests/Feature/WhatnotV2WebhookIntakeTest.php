<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatnotV2WebhookIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_intake_is_disabled_by_default(): void
    {
        $this->postJson('/integrations/whatnot/v2/webhook', ['type' => 'orders.created'])
            ->assertStatus(503);
    }

    public function test_signed_delivery_is_deduplicated_without_operational_writes(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $secret = random_bytes(32);
        config()->set('services.whatnot_seller.webhooks_enabled', true);
        config()->set('services.whatnot_seller.webhook_secret', 'wn_wh_'.base64_encode($secret));
        config()->set('services.whatnot_seller.webhook_seller_ids', 'seller-1');

        $body = json_encode([
            'type' => 'orders.created', 'timestamp' => now()->toISOString(),
            'version' => 'v2', 'seller_id' => 'seller-1',
            'data' => ['id' => 'order-1', 'show_id' => '0b6f3c1e-8a52-4d2e-9f7a-3c5d1e2f4a6b'],
        ], JSON_THROW_ON_ERROR);
        $id = '0b7e4f6c-3d1a-4a8e-9f2b-5c6d7e8f9a0b';
        $timestamp = (string) time();
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $secret, true));
        $headers = [
            'webhook-id' => $id, 'webhook-timestamp' => $timestamp,
            'webhook-signature' => $signature, 'Content-Type' => 'application/json',
        ];

        $this->call('POST', '/integrations/whatnot/v2/webhook', [], [], [], $this->headersToServer($headers), $body)
            ->assertStatus(202);
        $this->call('POST', '/integrations/whatnot/v2/webhook', [], [], [], $this->headersToServer($headers), $body)
            ->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, DB::table('whatnot_api_webhook_events')->count());
        $this->assertSame(0, DB::table('shows')->count());
        $this->assertSame(0, DB::table('whatnot_show_orders')->count());
    }

    private function headersToServer(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        return $result;
    }
}
