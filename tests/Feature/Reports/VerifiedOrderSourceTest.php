<?php
namespace Tests\Feature\Reports;

use App\Models\{Show, Streamer, WhatnotChannel, WhatnotLedgerEntry, WhatnotShowOrder};
use App\Services\{LedgerAutoMatcher, LedgerAttributionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerifiedOrderSourceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $channel=WhatnotChannel::create(['name'=>'Evidence channel','whatnot_username'=>'evidence']);
        $live='11111111-1111-4111-8111-111111111111';
        $show=Show::withoutEvents(fn()=>Show::create(['title'=>'Verified show','show_date'=>today()->subDay(),'status'=>'draft','whatnot_channel_id'=>$channel->id,'whatnot_show_id'=>$live]));
        $host=Streamer::create(['name'=>'Primary','status'=>'active']);
        $guest=Streamer::create(['name'=>'Guest','status'=>'active']);
        $show->streamers()->attach($host->id,['is_primary'=>true]);
        $show->streamers()->attach($guest->id,['is_primary'=>false]);
        $entry=WhatnotLedgerEntry::create(['whatnot_channel_id'=>$channel->id,'whatnot_order_id'=>'123','dedup_key'=>'source-test','amount'=>25,'transaction_type'=>'Sales']);
        $e=['version'=>1,'method'=>'seller_hub_filtered_response','live_id'=>$live,'order_id'=>'123','pagination_complete'=>true];
        $order=WhatnotShowOrder::create(['show_id'=>$show->id,'whatnot_order_id'=>'123','raw_data'=>['_source_evidence'=>$e]]);
        return [$show,$host,$entry,$order];
    }

    public function test_verified_match_automatically_assigns_primary_and_is_idempotent(): void
    {
        [$show,$host,$entry]=$this->fixture();
        $matcher=app(LedgerAutoMatcher::class);
        $this->assertSame('matched',$matcher->match($entry));
        $this->assertSame('matched',$matcher->match($entry));
        $row=DB::table('ledger_attributions')->sole();
        $this->assertSame($show->id,(int)$row->show_id);
        $this->assertSame($host->id,(int)$row->streamer_id);
        $this->assertSame('approved',$row->status);
        $this->assertNull($row->submitted_by);
        $this->assertSame(1,DB::table('ledger_attribution_events')->count());
        $this->assertSame(25.0,(float)$entry->fresh()->amount);
    }

    public function test_legacy_link_without_source_evidence_is_not_verified(): void
    {
        [,, $entry,$order]=$this->fixture();
        $order->update(['raw_data'=>[]]);
        $this->assertNull(app(LedgerAttributionService::class)->suggestedShow($entry));
    }

    public function test_sampled_or_wrong_order_or_wrong_show_evidence_cannot_match(): void
    {
        [,, $entry,$order]=$this->fixture();
        $original=$order->raw_data;
        foreach (['pagination_complete'=>false,'order_id'=>'456','live_id'=>'22222222-2222-4222-8222-222222222222'] as $key=>$value) {
            $raw=$original;$raw['_source_evidence'][$key]=$value;$order->update(['raw_data'=>$raw]);
            $this->assertNull(app(LedgerAttributionService::class)->suggestedShow($entry));
        }
    }

    public function test_wrong_channel_cannot_match(): void
    {
        [,, $entry]=$this->fixture();
        $entry->whatnot_channel_id=null;
        $this->assertNull(app(LedgerAttributionService::class)->suggestedShow($entry));
    }

    public function test_manual_decisions_are_never_overwritten(): void
    {
        [,, $entry]=$this->fixture();
        $user=\App\Models\User::factory()->create();
        DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id'=>$entry->id,'target'=>'house','category'=>'other','status'=>'pending','submitted_by'=>$user->id,'reason'=>'Existing decision','created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame('manual',app(LedgerAutoMatcher::class)->match($entry));
        $this->assertSame('house',DB::table('ledger_attributions')->sole()->target);
    }
    public function test_automatic_match_preserves_approved_historical_streamer_when_primary_changes(): void
    {
        [$show, $original, $entry] = $this->fixture();
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('matched', $matcher->match($entry));
        $replacement = Streamer::create(['name' => 'Replacement', 'status' => 'active']);
        $show->streamers()->updateExistingPivot($original->id, ['is_primary' => false]);
        $show->streamers()->attach($replacement->id, ['is_primary' => true]);
        $this->assertSame('matched', $matcher->match($entry));
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
        $this->assertSame($original->id, (int) $row->streamer_id);
        $this->assertSame(1, DB::table('ledger_attribution_events')->count());
    }

    public function test_missing_evidence_does_not_revoke_existing_approved_attribution(): void
    {
        [,, $entry, $order] = $this->fixture();
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('matched', $matcher->match($entry));
        $order->update(['raw_data' => []]);
        $this->assertSame('flagged', $matcher->match($entry));
        $this->assertSame('approved', DB::table('ledger_attributions')->sole()->status);
    }

    public function test_conflicting_verified_show_never_reassigns_approved_ledger_entry(): void
    {
        [$originalShow, $originalStreamer, $entry, $order] = $this->fixture();
        $matcher = app(LedgerAutoMatcher::class);
        $this->assertSame('matched', $matcher->match($entry));

        $otherLive = '22222222-2222-4222-8222-222222222222';
        $otherShow = Show::withoutEvents(fn () => Show::create([
            'title' => 'Different verified show',
            'show_date' => today()->subDay(),
            'status' => 'draft',
            'whatnot_channel_id' => $entry->whatnot_channel_id,
            'whatnot_show_id' => $otherLive,
        ]));
        $raw = $order->raw_data;
        $raw['_source_evidence']['live_id'] = $otherLive;
        $order->update(['show_id' => $otherShow->id, 'raw_data' => $raw]);

        $this->assertSame('flagged', $matcher->match($entry));
        $attribution = DB::table('ledger_attributions')->sole();
        $this->assertSame('approved', $attribution->status);
        $this->assertSame($originalShow->id, (int) $attribution->show_id);
        $this->assertSame($originalStreamer->id, (int) $attribution->streamer_id);
        $this->assertSame(1, DB::table('ledger_attribution_events')->count());
    }

}
