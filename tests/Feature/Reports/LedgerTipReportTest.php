<?php

namespace Tests\Feature\Reports;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Filament\Resources\WhatnotLedgerResource;
use App\Filament\Resources\WhatnotLedgerResource\Pages\LedgerTips;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use App\Services\LedgerTipReport;
use App\Support\ChannelContext;
use App\Support\PaySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerTipReportTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->channel = WhatnotChannel::create(['name' => 'Shared channel', 'status' => 'active']);
        $this->business = Business::where('short_name', 'Estill')->firstOrFail();
    }

    private function tip(?float $amount, array $extra = []): WhatnotLedgerEntry
    {
        return WhatnotLedgerEntry::create($extra + [
            'whatnot_channel_id' => $this->channel->id, 'created_date' => '2026-10-22 12:00:00',
            'amount' => $amount, 'transaction_type' => 'Tips', 'status' => 'Completed',
            'dedup_key' => fake()->uuid(),
        ]);
    }

    private function order(string $order, ?Business $business = null, ?WhatnotChannel $channel = null): Show
    {
        $member = Streamer::create(['name' => fake()->name(), 'business_id' => $business?->id, 'status' => 'active']);
        $show = Show::create(['title' => 'Tip show', 'show_date' => '2026-10-22', 'created_by' => $this->owner->id,
            'whatnot_channel_id' => ($channel ?? $this->channel)->id, 'status' => 'reconciled']);
        $show->streamers()->attach($member->id, ['is_primary' => true]);
        WhatnotShowOrder::create(['show_id' => $show->id, 'whatnot_order_id' => $order]);

        return $show;
    }

    private function report(string $business = 'all'): array
    {
        return app(LedgerTipReport::class)->between('2026-10-01', '2026-10-31', 'all', $business);
    }

    public function test_imported_signed_tips_include_last_day_and_exclude_other_types_and_dates(): void
    {
        $this->tip(20.01, ['transaction_type' => ' TIP ', 'created_date' => '2026-10-01 00:00:00']);
        $this->tip(-5.02, ['created_date' => '2026-10-31 23:59:59']);
        $this->tip(3, ['status' => 'Processing']);
        $this->tip(null);
        $this->tip(999, ['transaction_type' => 'Sales Earnings']);
        $this->tip(999, ['created_date' => '2026-11-01 00:00:00']);
        $this->tip(999, ['created_date' => '2026-09-30 23:59:59']);
        $report = $this->report();
        $this->assertSame(['credits' => 2301, 'debits' => 502, 'net' => 1799, 'entries' => 4, 'pending' => 1, 'missing_amounts' => 1], $report['total']);
        $this->assertSame('Shared channel', $report['rows'][0]['channel']);
        $this->assertSame('Unassigned business', $report['rows'][0]['business']);
    }

    public function test_shared_channel_does_not_assign_a_business_and_unknown_tips_stay_separate(): void
    {
        $this->order('estill-order', $this->business);
        $other = Business::where('short_name', 'VCX')->firstOrFail();
        $this->order('vcx-order', $other);
        $this->tip(50, ['whatnot_order_id' => 'estill-order']);
        $this->tip(80, ['whatnot_order_id' => 'vcx-order']);
        $this->tip(15);
        $report = $this->report((string) $this->business->id);
        $this->assertSame(5000, $report['total']['net']);
        $this->assertSame([5000], array_column($report['days'], 'net'));
        $this->assertCount(1, $report['rows']);
        $this->assertSame(1500, $report['unassigned_total']['net']);
        $this->assertSame(1500, $this->report('unassigned')['total']['net']);
        $this->assertSame(14500, $this->report()['total']['net']);
    }

    public function test_multiple_streamer_matches_never_multiply_tips_and_ambiguous_shows_are_unassigned(): void
    {
        $show = $this->order('duplicate', $this->business);
        $member = Streamer::create(['name' => 'Collaborator', 'status' => 'active', 'business_id' => $this->business->id]);
        $show->streamers()->attach($member->id);
        $this->tip(10, ['whatnot_order_id' => 'duplicate']);
        $this->assertSame(1000, $this->report((string) $this->business->id)['total']['net']);
        $this->order('duplicate', $this->business);
        $this->assertSame(0, $this->report((string) $this->business->id)['total']['net']);
        $this->assertSame(1000, $this->report('unassigned')['total']['net']);
    }

    public function test_mixed_business_show_and_different_channel_order_do_not_attribute_tips(): void
    {
        $show = $this->order('mixed', $this->business);
        $member = Streamer::create(['name' => 'Other business', 'status' => 'active', 'business_id' => Business::where('short_name', 'VCX')->value('id')]);
        $show->streamers()->attach($member->id);
        $this->tip(20, ['whatnot_order_id' => 'mixed']);
        $different = WhatnotChannel::create(['name' => 'Different', 'status' => 'active']);
        $this->order('different-channel', $this->business, $different);
        $this->tip(30, ['whatnot_order_id' => 'different-channel']);
        $this->assertSame(5000, $this->report('unassigned')['total']['net']);
    }

    public function test_missing_business_members_and_second_show_without_members_are_unassigned(): void
    {
        $show = $this->order('missing-business', $this->business);
        $member = Streamer::create(['name' => 'No business', 'status' => 'active']);
        $show->streamers()->attach($member->id);
        $this->tip(20, ['whatnot_order_id' => 'missing-business']);
        $this->order('two-shows', $this->business);
        $second = Show::create(['title' => 'No members', 'show_date' => '2026-10-22', 'whatnot_channel_id' => $this->channel->id, 'created_by' => $this->owner->id]);
        WhatnotShowOrder::create(['show_id' => $second->id, 'whatnot_order_id' => 'two-shows']);
        $this->tip(30, ['whatnot_order_id' => 'two-shows']);
        $this->assertSame(5000, $this->report('unassigned')['total']['net']);
    }

    public function test_channel_context_and_unassigned_channel_filter_are_respected(): void
    {
        $this->tip(10);
        $this->tip(20, ['whatnot_channel_id' => null]);
        $other = WhatnotChannel::create(['name' => 'Another channel', 'status' => 'inactive']);
        $this->tip(30, ['whatnot_channel_id' => $other->id]);
        $this->assertCount(3, $this->report()['rows']);
        $this->assertSame(2000, app(LedgerTipReport::class)->between('2026-10-01', '2026-10-31', 'unassigned')['total']['net']);
        ChannelContext::setActive($this->channel->id);
        $this->assertSame(1000, $this->report()['total']['net']);
        $this->assertSame(0, app(LedgerTipReport::class)->between('2026-10-01', '2026-10-31', (string) $other->id)['total']['net']);
    }

    public function test_weekly_breakdown_uses_payroll_start_day_and_clips_to_selected_dates(): void
    {
        Setting::set(PaySchedule::SETTING, 5); // Friday
        $this->tip(10, ['created_date' => '2026-10-01 12:00:00']);
        $this->tip(20, ['created_date' => '2026-10-02 12:00:00']);
        $report = $this->report();
        $this->assertSame(['2026-09-25', '2026-10-02'], array_column($report['weeks'], 'week'));
        $this->assertSame([1000, 2000], array_column($report['weeks'], 'net'));
        $this->assertSame(['2026-10-01', '2026-10-02'], array_column($report['days'], 'day'));
        $this->assertSame([1000, 2000], array_column($report['days'], 'net'));
    }

    public function test_page_renders_filters_and_totals_and_rejects_invalid_custom_range(): void
    {
        $this->tip(40);
        $page = Livewire::test(LedgerTips::class)
            ->set('from', '2026-10-01')->set('until', '2026-10-31')
            ->assertSee('Net ledger tips')->assertSee('$40.00')->assertSee('Daily trend')
            ->assertSee('Tip credits by channel')->assertSee('Weekly breakdown')->assertSee('Export CSV')
            ->assertSee('Unassigned business');
        $page->set('until', '2026-09-01')->assertHasErrors('until')->assertDontSee('Net ledger tips');
        $page->set('until', '2026-10-31')->assertSee('$40.00')
            ->call('resetFilters')->assertSet('period', 'week')->assertSet('from', null);
        $this->withoutVite()->get(WhatnotLedgerResource::getUrl('index'))->assertOk()->assertSee('Tip totals');
    }

    public function test_pay_run_dates_and_business_summary_are_read_only(): void
    {
        $show = $this->order('paid-tips', $this->business);
        $this->tip(50, ['whatnot_order_id' => 'paid-tips']);
        $run = WeeklyPayoutBatch::create(['business_id' => $this->business->id, 'week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft', 'created_by' => $this->owner->id]);
        $payout = Payout::create(['weekly_payout_batch_id' => $run->id, 'show_id' => $show->id, 'streamer_id' => $show->streamers()->first()->id,
            'payout_type' => 'package', 'calculated_payout' => 75, 'tips_included' => 25, 'status' => 'draft']);
        $page = Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->id])
            ->assertSee('Ledger tip totals')->assertSee('$50.00')->assertSee('Tips recorded in this run: $25.00');
        $this->assertSame(5000, $page->instance()->assistantAndTipSummary()['tips']['total']['net']);
        $this->assertSame('75.00', $payout->fresh()->calculated_payout);
        Livewire::withQueryParams(['period' => 'pay_period', 'payRun' => $run->id, 'business' => (string) $this->business->id])
            ->test(LedgerTips::class)->assertSee('Oct 19, 2026')->assertSee('Oct 25, 2026')->assertSee('$50.00');
        $this->assertSame(1, Payout::count());
    }

    public function test_filtered_weekly_breakdown_can_be_downloaded(): void
    {
        $this->tip(40);
        Livewire::test(LedgerTips::class)
            ->set('from', '2026-10-01')->set('until', '2026-10-31')
            ->set('channel', (string) $this->channel->id)
            ->call('exportCsv')
            ->assertFileDownloaded('ledger-tip-totals-2026-10-01-2026-10-31.csv');
    }

    public function test_regular_user_cannot_open_tip_report(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(WhatnotLedgerResource::getUrl('tips'))->assertForbidden();
    }

    public function test_month_and_previous_month_presets_keep_calendar_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'America/Chicago'));
        $this->tip(10, ['created_date' => '2026-10-31 23:59:59']);
        $this->tip(20, ['created_date' => '2026-11-01 00:00:00']);
        $page = Livewire::test(LedgerTips::class)->set('period', 'last_month');
        $this->assertSame(1000, $page->instance()->report()['total']['net']);
        $page->set('period', 'month');
        $this->assertSame(2000, $page->instance()->report()['total']['net']);
    }
}
