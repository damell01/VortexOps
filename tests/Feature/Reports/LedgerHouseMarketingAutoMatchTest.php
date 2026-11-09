<?php

namespace Tests\Feature\Reports;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerHouseMarketingAutoMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_orderless_marketing_debit_requires_review_once(): void
    {
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'house-marketing-1',
            'transaction_type' => 'Marketing',
            'amount' => -25,
        ]);
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('categorized', $matcher->match($entry));
        $this->assertSame('unmatched', $matcher->match($entry));
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
        $this->assertSame('house', $row->target);
        $this->assertSame('pending', $row->status);
        $this->assertSame('marketing', $row->category);
        $this->assertNull($row->streamer_id);
        $this->assertNull($row->business_id);
        $this->assertSame(1, DB::table('ledger_attribution_events')->where('whatnot_ledger_entry_id', $entry->id)->count());
    }

    public function test_marketing_order_or_refund_cannot_be_inferred_as_house(): void
    {
        $ordered = WhatnotLedgerEntry::create([
            'dedup_key' => 'marketing-order-1', 'transaction_type' => 'Marketing',
            'whatnot_order_id' => 'order-123', 'amount' => -10,
        ]);
        $refund = WhatnotLedgerEntry::create([
            'dedup_key' => 'refund-1', 'transaction_type' => 'Refund',
            'amount' => -10,
        ]);
        $credit = WhatnotLedgerEntry::create([
            'dedup_key' => 'marketing-credit-1', 'transaction_type' => 'Marketing',
            'amount' => 10,
        ]);
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('categorized', $matcher->match($ordered));
        $this->assertSame('categorized', $matcher->match($refund));
        $this->assertSame('categorized', $matcher->match($credit));
        $this->assertSame(3, DB::table('ledger_attributions')->where('target', 'unassigned')->where('status', 'pending')->count());
    }
    public function test_explicit_orderless_bonus_debit_is_suggested_but_never_approved(): void
    {
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'house-bonus-1',
            'transaction_type' => 'Bonus',
            'amount' => -40,
        ]);
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('categorized', $matcher->match($entry));
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
        $this->assertSame('bonus', $row->category);
        $this->assertSame('house', $row->target);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->business_id);
        $this->assertNull($row->streamer_id);
    }

}
