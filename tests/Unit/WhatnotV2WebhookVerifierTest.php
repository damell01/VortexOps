<?php

namespace Tests\Unit;

use App\Services\WhatnotV2WebhookVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WhatnotV2WebhookVerifierTest extends TestCase
{
    public function test_accepts_valid_v2_signature(): void
    {
        $id = '0b7e4f6c-3d1a-4a8e-9f2b-5c6d7e8f9a0b';
        $time = (string) time();
        $body = json_encode(['type' => 'orders.created', 'timestamp' => gmdate('c'), 'version' => 'v2', 'seller_id' => '123', 'data' => ['id' => '456']]);
        $secret = random_bytes(32);
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$time.'.'.$body, $secret, true));
        $result = (new WhatnotV2WebhookVerifier())->verify($body, $id, $time, $signature, 'wn_wh_'.base64_encode($secret));
        self::assertSame('orders.created', $result['type']);
    }

    public function test_rejects_tampered_body(): void
    {
        $this->expectException(RuntimeException::class);
        $id = '0b7e4f6c-3d1a-4a8e-9f2b-5c6d7e8f9a0b';
        $time = (string) time();
        $secret = random_bytes(32);
        $body = '{"type":"orders.created","version":"v2","seller_id":"123","data":{}}';
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$time.'.'.$body, $secret, true));
        (new WhatnotV2WebhookVerifier())->verify($body.' ', $id, $time, $signature, 'wn_wh_'.base64_encode($secret));
    }

    public function test_rejects_old_delivery(): void
    {
        $this->expectException(RuntimeException::class);
        (new WhatnotV2WebhookVerifier())->verify('{}', '0b7e4f6c-3d1a-4a8e-9f2b-5c6d7e8f9a0b', (string) (time()-600), 'v1,invalid', 'wn_wh_'.base64_encode(random_bytes(32)));
    }
}
