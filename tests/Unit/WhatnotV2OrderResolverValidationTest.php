<?php

namespace Tests\Unit;

use App\Services\WhatnotV2OrderResolver;
use PHPUnit\Framework\TestCase;

class WhatnotV2OrderResolverValidationTest extends TestCase
{
    public function test_rejects_empty_order_identifier_without_database_access(): void
    {
        $result = (new WhatnotV2OrderResolver())->resolve(1, ['id' => '']);
        self::assertSame('invalid_order_id', $result['status']);
    }

    public function test_requires_canonical_show_identifier_before_database_access(): void
    {
        $result = (new WhatnotV2OrderResolver())->resolve(1, ['id' => 'order-1', 'show_id' => 'unknown']);
        self::assertSame('missing_verified_show_id', $result['status']);
    }
}
