<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerHouseMarketingAutoMatchTest extends TestCase
{
    use RefreshDatabase;

    private function entry(string $key, string $type, float $amount, array $extra = []): WhatnotLedgerEntry
    {
        return WhatnotLedgerEntry::create($extra + ['dedup_key' => $key, 'transaction_type' => $type, 'amount' => $amount]);
    }

    private function row(WhatnotLedgerEntry $entry): object
    {
        return DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
    }

    public function test_orderless_marketing_and_bonuses_are_filed_to_the_house_automatically(): void
    {
        $matcher = app(LedgerAutoMatcher::class);
        foreach ([['promo', 'Marketing', -25, 'marketing'], ['promo-credit', 'Promotion refund', 10, 'marketing'],
                  ['boost', 'PROMOTED_LISTING', -4, 'marketing'], ['bonus', 'Seller bonus', 50, 'bonus']] as [$key, $type, $amount, $category]) {
            $entry = $this->entry($key, $type, $amount);
            $this->assertSame('matched', $matcher->match($entry), $type);
            $this->assertSame('matched', $matcher->match($entry), $type.' again');
            $row = $this->row($entry);
            $this->assertSame(['house', 'approved', $category], [$row->target, $row->status, $row->category], $type);
            $this->assertNull($row->show_id);
            $this->assertNull($row->streamer_id);
            $this->assertSame(1, DB::table('ledger_attribution_events')->where('whatnot_ledger_entry_id', $entry->id)->count());
        }
    }

    public function test_an_earlier_pending_suggestion_is_upgraded(): void
    {
        $entry = $this->entry('old-suggestion', 'Marketing', -25);
        DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $entry->id, 'category' => 'marketing', 'target' => 'house',
            'status' => 'pending', 'reason' => 'Suggested house marketing expense', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('matched', app(LedgerAutoMatcher::class)->match($entry));
        $this->assertSame('approved', $this->row($entry)->status);
    }

    public function test_order_or_stream_backed_entries_tips_refunds_and_human_decisions_stay_out(): void
    {
        $matcher = app(LedgerAutoMatcher::class);
        $ordered = $this->entry('marketing-order', 'Marketing', -10, ['whatnot_order_id' => 'order-123']);
        $streamed = $this->entry('bonus-stream', 'Bonus', 20, ['raw_data' => ['livestream_id' => '6b1f0b4e-2d1a-4c7e-9f4a-1a2b3c4d5e6f']]);
        $refund = $this->entry('refund', 'Refund', -10);
        $tip = $this->entry('tip', 'Tip', 5);
        foreach ([$ordered, $streamed, $refund, $tip] as $entry) {
            $this->assertSame('categorized', $matcher->match($entry));
            $this->assertSame(['unassigned', 'pending'], [$this->row($entry)->target, $this->row($entry)->status]);
        }

        $rejected = $this->entry('rejected', 'Marketing', -8);
        DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $rejected->id, 'category' => 'marketing', 'target' => 'house',
            'status' => 'rejected', 'reason' => 'Suggested', 'reviewed_by' => User::factory()->create()->id, 'reviewed_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('manual', $matcher->match($rejected));
        $this->assertSame('rejected', $this->row($rejected)->status);
    }
}
