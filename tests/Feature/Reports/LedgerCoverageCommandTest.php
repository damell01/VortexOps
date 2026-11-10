<?php

namespace Tests\Feature\Reports;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerCoverageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_counts_pending_marketing_and_refund_without_mutating_ledger(): void
    {
        $marketing = WhatnotLedgerEntry::create([
            'dedup_key' => 'coverage-marketing', 'transaction_type' => 'Marketing', 'amount' => -5,
        ]);
        WhatnotLedgerEntry::create([
            'dedup_key' => 'coverage-refund', 'transaction_type' => 'Refund', 'amount' => -2,
        ]);
        $this->artisan('ledger:match-shows', ['--limit' => 2])->assertSuccessful();
        $this->artisan('ledger:coverage --json')->assertSuccessful();
        $this->assertSame(0, DB::table('whatnot_ledger_entries as e')
            ->leftJoin('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'e.id')
            ->whereNull('a.id')->count());
        $this->assertSame(2, WhatnotLedgerEntry::count());
        $this->assertSame(2, DB::table('ledger_attributions')->count());
    }

    public function test_business_summary_only_includes_approved_attributions(): void
    {
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'summary-refund', 'transaction_type' => 'Refund', 'amount' => -12,
        ]);
        $this->artisan('ledger:match-shows', ['--limit' => 1])->assertSuccessful();
        $this->artisan('ledger:business-summary --json')
            ->assertSuccessful();
        $this->assertSame(-12.0, (float) DB::table('whatnot_ledger_entries')->where('id', $entry->id)->value('amount'));
        $this->assertSame('pending', DB::table('ledger_attributions')
            ->where('whatnot_ledger_entry_id', $entry->id)->value('status'));
    }
}
