<?php

namespace Tests\Feature\Reports;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TipAttributionIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_house_tips_and_missing_attribution_without_changing_payroll(): void
    {
        $house = WhatnotLedgerEntry::create([
            'dedup_key' => 'integrity-house-tip', 'transaction_type' => 'Tips',
            'amount' => 15, 'created_date' => now(),
        ]);
        $unassigned = WhatnotLedgerEntry::create([
            'dedup_key' => 'integrity-unassigned-tip', 'transaction_type' => 'Tip',
            'amount' => 10, 'created_date' => now(),
        ]);
        DB::table('ledger_attributions')->insert([
            'whatnot_ledger_entry_id' => $house->id, 'category' => 'tips',
            'target' => 'house', 'status' => 'approved', 'reason' => 'Legacy assignment',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('whatnot:audit-tip-attribution')->assertSuccessful();
        $this->assertDatabaseHas('ledger_attributions', [
            'whatnot_ledger_entry_id' => $house->id,
            'target' => 'house', 'status' => 'approved',
        ]);
        $this->assertDatabaseMissing('ledger_attributions', [
            'whatnot_ledger_entry_id' => $unassigned->id,
        ]);
    }

    public function test_audit_does_not_reassign_historical_approved_primary_streamer(): void
    {
        $primary = Streamer::create(['name' => 'Current primary', 'status' => 'active']);
        $previous = Streamer::create(['name' => 'Historical primary', 'status' => 'active']);
        $show = Show::withoutEvents(fn () => Show::create([
            'title' => 'Tip audit show', 'show_date' => today(), 'status' => 'reconciled',
        ]));
        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'integrity-historical-tip', 'transaction_type' => 'Tips',
            'amount' => 12, 'created_date' => now(),
        ]);
        DB::table('ledger_attributions')->insert([
            'whatnot_ledger_entry_id' => $entry->id, 'category' => 'tips',
            'target' => 'show', 'show_id' => $show->id, 'streamer_id' => $previous->id,
            'status' => 'approved', 'reason' => 'Historical approved assignment',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('whatnot:audit-tip-attribution')->assertSuccessful();
        $this->assertSame($previous->id, (int) DB::table('ledger_attributions')
            ->where('whatnot_ledger_entry_id', $entry->id)->value('streamer_id'));
    }
}
