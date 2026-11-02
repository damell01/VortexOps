<?php

namespace Tests\Feature\Reports;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerCoverageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_counts_approved_house_and_unmatched_without_mutating_ledger(): void
    {
        $marketing = WhatnotLedgerEntry::create([
            'dedup_key' => 'coverage-marketing', 'transaction_type' => 'Marketing', 'amount' => -5,
        ]);
        WhatnotLedgerEntry::create([
            'dedup_key' => 'coverage-refund', 'transaction_type' => 'Refund', 'amount' => -2,
        ]);
        $this->artisan('ledger:match-shows', ['--limit' => 2])->assertSuccessful();
        $this->artisan('ledger:coverage --json')->expectsOutputToContain('"approved_house":1')
            ->expectsOutputToContain('"unmatched":1')->assertSuccessful();
        $this->assertSame(2, WhatnotLedgerEntry::count());
        $this->assertSame(1, DB::table('ledger_attributions')->count());
    }
}
