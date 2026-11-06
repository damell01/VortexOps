<?php

namespace Tests\Unit;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAttributionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LedgerCategoryTest extends TestCase
{
    public static function types(): array
    {
        return [
            'tip' => ['TIP', 'tips'],
            'refund' => ['Order refund', 'refunds'],
            'carrier adjustment' => ['Carrier adjustment', 'shipping'],
            'shipping correction' => ['Shipping correction', 'shipping'],
            'marketing adjustment' => ['Marketing adjustment', 'marketing'],
            'bonus adjustment' => ['Bonus adjustment', 'bonus'],
            'fee adjustment' => ['Commission adjustment', 'fees'],
            'manual correction' => ['Manual correction', 'correction'],
            'order earnings' => ['Order earnings', 'sales'],
            'unknown' => ['Unknown', 'other'],
        ];
    }

    #[DataProvider('types')]
    public function test_financial_category_precedence(string $type, string $expected): void
    {
        $entry = new WhatnotLedgerEntry();
        $entry->transaction_type = $type;

        self::assertSame($expected, (new LedgerAttributionService())->suggestedCategory($entry));
    }
}
