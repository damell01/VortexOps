<?php

namespace Tests\Feature\Reports;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use App\Services\LedgerAttributionService;
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

    public function test_exact_order_match_suggests_show_but_duplicate_show_matches_do_not(): void
    {
        $author = User::factory()->create();
        $show = Show::withoutEvents(fn () => Show::create(['title' => 'Matching show', 'show_date' => today(), 'created_by' => $author->id, 'status' => 'reconciled']));
        $entry = WhatnotLedgerEntry::create(['dedup_key' => 'match-entry', 'whatnot_order_id' => 'order-123', 'transaction_type' => 'Tips']);
        WhatnotShowOrder::create(['show_id' => $show->id, 'whatnot_order_id' => 'order-123']);
        $service = app(LedgerAttributionService::class);
        $this->assertSame($show->id, $service->suggestedShow($entry)?->id);
        $this->assertSame('tips', $service->suggestedCategory($entry));

        $other = Show::withoutEvents(fn () => Show::create(['title' => 'Another show', 'show_date' => today(), 'created_by' => $author->id, 'status' => 'reconciled']));
        WhatnotShowOrder::create(['show_id' => $other->id, 'whatnot_order_id' => 'order-123']);
        $this->assertNull($service->suggestedShow($entry));
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
}
