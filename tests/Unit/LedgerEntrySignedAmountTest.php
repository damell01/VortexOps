<?php

namespace Tests\Unit;

use App\Models\LedgerEntry;
use PHPUnit\Framework\TestCase;

class LedgerEntrySignedAmountTest extends TestCase
{
    public function test_credit_adds_and_debit_subtracts(): void
    {
        $credit = new LedgerEntry(['amount' => '25.50', 'direction' => 'credit']);
        $debit = new LedgerEntry(['amount' => '25.50', 'direction' => 'debit']);

        $this->assertSame(25.50, $credit->signedAmount());
        $this->assertSame(-25.50, $debit->signedAmount());
    }
}
