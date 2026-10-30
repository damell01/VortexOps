<?php

namespace Tests\Unit;

use App\Services\WhatnotV2OrderMapper;
use PHPUnit\Framework\TestCase;

final class WhatnotV2OrderMapperTest extends TestCase
{
    private function order(): array
    {
        return [
            'id' => '123456',
            'show_id' => '0b6f3c1e-8a52-4d2e-9f7a-3c5d1e2f4a6b',
            'status' => 'pending_fulfillment',
            'amounts' => [
                'subtotal' => ['amount' => ['amount' => '42', 'currency' => 'USD']],
                'shipping' => ['amount' => ['amount' => '5.99', 'currency' => 'USD']],
                'tax' => ['amount' => ['amount' => '3.78', 'currency' => 'USD']],
                'total' => ['amount' => ['amount' => '49.77', 'currency' => 'USD']],
            ],
        ];
    }

    public function test_maps_official_order_webhook_example(): void
    {
        $result = (new WhatnotV2OrderMapper())->map($this->order());
        self::assertSame('123456', $result['whatnot_order_id']);
        self::assertSame('49.77', $result['total_usd']);
        self::assertSame('42', $result['subtotal_usd']);
    }

    public function test_rejects_non_usd_amounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $order = $this->order();
        $order['amounts']['total']['amount']['currency'] = 'CAD';
        (new WhatnotV2OrderMapper())->map($order);
    }

    public function test_rejects_missing_show_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $order = $this->order();
        unset($order['show_id']);
        (new WhatnotV2OrderMapper())->map($order);
    }
}
