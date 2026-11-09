<?php

namespace Tests\Feature\Reports;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use App\Services\LedgerAttributionService;
use App\Services\LedgerTipReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LedgerAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_assignment_uses_primary_streamer_and_requires_independent_approval(): void
    {
        $author = User::factory()->create();
        $reviewer = User::factory()->create();
        $primary = Streamer::create(['name' => 'Primary host', 'status' => 'active']);
        $guest = Streamer::create(['name' => 'Guest host', 'status' => 'active']);
        $show = Show::withoutEvents(fn () => Show::create(['title' => 'Test ledger show', 'show_date' => today(), 'status' => 'draft']));
        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $show->streamers()->attach($guest->id, ['is_primary' => false]);
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'manual-attribution-1', 'amount' => 25, 'transaction_type' => 'Tips',
            'created_date' => now(),
        ]);
        $service = app(LedgerAttributionService::class);
        $service->save($entry, ['category' => 'tips', 'target' => 'show', 'show_id' => $show->id, 'reason' => 'Verified against show'], $author->id);
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
        $this->assertSame($primary->id, $row->streamer_id);
        $this->assertSame('pending', $row->status);
        try {
            $service->review($entry, $author->id, true);
            $this->fail('Self approval must be blocked.');
        } catch (ValidationException $expected) {
            $this->assertSame('pending', DB::table('ledger_attributions')->where('id', $row->id)->value('status'));
        }
        $service->review($entry, $reviewer->id, true);
        $this->assertSame('approved', DB::table('ledger_attributions')->where('id', $row->id)->value('status'));
        $this->assertSame(2, DB::table('ledger_attribution_events')->where('whatnot_ledger_entry_id', $entry->id)->count());
        $this->assertSame(25.0, (float) $entry->fresh()->amount);
    }

    public function test_unverified_order_links_do_not_suggest_show_even_when_unique(): void
    {
        $author = User::factory()->create();
        $show = Show::withoutEvents(fn () => Show::create(['title' => 'Matching show', 'show_date' => today(), 'created_by' => $author->id, 'status' => 'reconciled']));
        $entry = WhatnotLedgerEntry::create(['dedup_key' => 'match-entry', 'whatnot_order_id' => 'order-123', 'transaction_type' => 'Tips']);
        WhatnotShowOrder::create(['show_id' => $show->id, 'whatnot_order_id' => 'order-123']);
        $service = app(LedgerAttributionService::class);
        $this->assertNull($service->suggestedShow($entry));
        $this->assertSame('tips', $service->suggestedCategory($entry));

        $other = Show::withoutEvents(fn () => Show::create(['title' => 'Another show', 'show_date' => today(), 'created_by' => $author->id, 'status' => 'reconciled']));
        WhatnotShowOrder::create(['show_id' => $other->id, 'whatnot_order_id' => 'order-123']);
        $this->assertNull($service->suggestedShow($entry));
    }

    public function test_approved_manual_show_assignment_is_counted_once_in_streamer_tip_totals(): void
    {
        $author = User::factory()->create();
        $reviewer = User::factory()->create();
        $primary = Streamer::create(['name' => 'Ledger report primary', 'status' => 'active']);
        $show = Show::withoutEvents(fn () => Show::create(['title' => 'Attributed show', 'show_date' => '2026-10-22', 'created_by' => $author->id, 'status' => 'reconciled']));
        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $entry = WhatnotLedgerEntry::create([
            'dedup_key' => 'attributed-tip-report', 'whatnot_order_id' => 'manual-tip-1',
            'amount' => 25, 'transaction_type' => 'Tips', 'status' => 'Completed', 'created_date' => '2026-10-22 12:00:00',
        ]);
        $service = app(LedgerAttributionService::class);
        $service->save($entry, ['category' => 'tips', 'target' => 'show', 'show_id' => $show->id, 'reason' => 'Verified show receipt'], $author->id);
        $this->assertSame([], app(LedgerTipReport::class)->byStreamer('2026-10-01', '2026-10-31'));
        $service->review($entry, $reviewer->id, true);
        $rows = app(LedgerTipReport::class)->byStreamer('2026-10-01', '2026-10-31');
        $this->assertCount(1, $rows);
        $this->assertSame('Ledger report primary', $rows[0]['streamer']);
        $this->assertSame(2500, $rows[0]['net']);
        $this->assertSame(1, $rows[0]['entries']);
    }

    public function test_house_assignment_has_no_streamer_and_can_be_rejected(): void
    {
        $author = User::factory()->create();
        $reviewer = User::factory()->create();
        $entry = WhatnotLedgerEntry::create(['dedup_key' => 'manual-attribution-2', 'amount' => -10, 'transaction_type' => 'Fees']);
        $service = app(LedgerAttributionService::class);
        $service->save($entry, ['category' => 'fees', 'target' => 'house', 'reason' => 'Platform fee'], $author->id);
        $this->assertNull(DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->value('streamer_id'));
        $service->review($entry, $reviewer->id, false);
        $this->assertSame('rejected', DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->value('status'));
    }
    public function test_automatic_unassigned_suggestion_cannot_be_approved(): void
    {
        $reviewer = User::factory()->create();
        $entry = WhatnotLedgerEntry::create(['dedup_key' => 'unassigned-approval-guard', 'amount' => -5, 'transaction_type' => 'Refund']);
        DB::table('ledger_attributions')->insert([
            'whatnot_ledger_entry_id' => $entry->id, 'category' => 'refunds',
            'target' => 'unassigned', 'status' => 'pending', 'reason' => 'Unverified recipient',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(ValidationException::class);
        app(LedgerAttributionService::class)->review($entry, $reviewer->id, true);
    }

    public function test_automatic_house_suggestion_without_business_cannot_be_approved(): void
    {
        $reviewer = User::factory()->create();
        $entry = WhatnotLedgerEntry::create(['dedup_key' => 'house-approval-guard', 'amount' => -25, 'transaction_type' => 'Marketing']);
        DB::table('ledger_attributions')->insert([
            'whatnot_ledger_entry_id' => $entry->id, 'category' => 'marketing',
            'target' => 'house', 'status' => 'pending', 'reason' => 'Awaiting business assignment',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(ValidationException::class);
        app(LedgerAttributionService::class)->review($entry, $reviewer->id, true);
    }

}
