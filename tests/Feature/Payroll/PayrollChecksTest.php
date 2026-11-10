<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\MyLedgerCharges;
use App\Filament\Pages\PayChangedSincePaid;
use App\Filament\Pages\PayrollOverview;
use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Filament\Resources\WhatnotLedgerResource;
use App\Livewire\LedgerCharges;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\BusinessChannelNet;
use App\Services\LedgerChargeReport;
use App\Services\PayChangedAfterPayout;
use App\Support\PayRunSchedules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Payroll checks: VCX streamers' Whatnot net per channel, tips on a run only
 * for its people (and on "All pay at once"), ledger charges for Estill
 * streamers, and pay that changed after it was locked.
 */
class PayrollChecksTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Business $vcx;
    private Business $estill;
    private WhatnotChannel $breaks;
    private WhatnotChannel $cards;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'reporting', 'inventory']));
        \App\Support\AdminModules::flushMemo();
        $this->vcx = Business::where('short_name', 'VCX')->firstOrFail();
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();
        $this->breaks = WhatnotChannel::create(['name' => 'VortexBreaks', 'status' => 'active']);
        $this->cards = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function member(string $name, Business $business): Streamer
    {
        return Streamer::create(['name' => $name, 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'business_id' => $business->id, 'compensation_override_fields' => [], 'payout_type' => 'profit_share', 'payout_percentage' => 10]);
    }

    private function show(Streamer $host, WhatnotChannel $channel, float $net, ?string $date = null): Show
    {
        $show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => "{$host->name} on {$channel->name} " . uniqid(),
            'show_date' => $date ?? now()->toDateString(), 'gross_revenue' => $net * 1.2, 'whatnot_net' => $net, 'tips' => 0,
            'units_sold' => 5, 'show_duration' => 120, 'status' => 'reconciled', 'created_by' => $this->owner->id]);
        $show->streamers()->attach($host->id, ['is_primary' => true]);

        return $show;
    }

    private function ledger(string $type, float $amount, ?Streamer $streamer, string $category, ?Show $show = null, string $status = 'approved'): int
    {
        $id = DB::table('whatnot_ledger_entries')->insertGetId(['whatnot_channel_id' => $this->breaks->id, 'created_date' => now()->subHour(),
            'amount' => $amount, 'transaction_type' => $type, 'whatnot_order_id' => '555' . random_int(100, 999), 'message' => $type,
            'status' => 'Completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $id, 'category' => $category, 'target' => $show ? 'show' : 'streamer',
            'show_id' => $show?->id, 'streamer_id' => $streamer?->id, 'business_id' => $streamer?->business_id, 'status' => $status,
            'reason' => 'test', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_vcx_streamers_whatnot_net_is_added_up_per_channel_on_overview_and_each_pay_run(): void
    {
        $nat = $this->member('Natbat', $this->vcx);
        $jay = $this->member('Jay', $this->vcx);
        $estiller = $this->member('Estill Ed', $this->estill);
        $this->show($nat, $this->breaks, 400);
        $this->show($jay, $this->breaks, 250.50);
        $this->show($nat, $this->cards, 300);
        $this->show($estiller, $this->cards, 9999); // another business: never counted for VCX
        $this->show($nat, $this->cards, 777, now()->subMonths(3)->toDateString()); // outside the period

        $net = app(BusinessChannelNet::class)->between($this->vcx->id, now()->subDays(6)->toDateString(), now()->toDateString());
        $this->assertSame('VCX', $net['business']);
        $this->assertSame(950.5, $net['total']);
        $this->assertSame(3, $net['shows']);
        $this->assertSame(['VortexBreaks' => 650.5, 'Vortex Cards' => 300.0], collect($net['channels'])->pluck('net', 'channel')->all());

        Livewire::test(PayrollOverview::class)->assertSee('VCX streamers · Whatnot net')->assertSee('$950.50')->assertSee('VortexBreaks')->assertSee('$650.50');

        $run = WeeklyPayoutBatch::create(['business_id' => $this->vcx->id, 'week_start' => now()->subDays(6)->toDateString(), 'week_end' => now()->toDateString(), 'status' => 'draft']);
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->getRouteKey()])
            ->assertSee('VCX streams · Whatnot net')->assertSee('$950.50')->assertSee('Vortex Cards');
    }

    public function test_a_pay_run_only_shows_tips_for_its_own_people_and_all_pay_includes_tip_lines(): void
    {
        $nat = $this->member('Natbat', $this->vcx);
        $estiller = $this->member('Estill Ed', $this->estill);
        $natShow = $this->show($nat, $this->breaks, 400);
        $edShow = $this->show($estiller, $this->breaks, 400);
        $this->ledger('Tip', 25, $nat, 'tips', $natShow);
        $this->ledger('Tip', 90, $estiller, 'tips', $edShow);

        $run = WeeklyPayoutBatch::create(['business_id' => $this->vcx->id, 'week_start' => now()->subDays(6)->toDateString(), 'week_end' => now()->toDateString(), 'status' => 'draft']);
        Payout::create(['show_id' => $natShow->id, 'streamer_id' => $nat->id, 'weekly_payout_batch_id' => $run->id, 'payout_type' => 'profit_share',
            'calculated_payout' => 40, 'gross_show_revenue' => 480, 'owner_fee_deducted' => 0, 'tips_included' => 0, 'status' => 'draft']);

        $page = Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->getRouteKey()]);
        $tips = $page->instance()->runStreamerTips();
        $this->assertSame(['Natbat'], array_column($tips, 'streamer'));
        $page->assertSee('Natbat')->assertDontSee('Estill Ed');

        // "All pay at once" pays tip lines too; wages and share runs still leave them.
        $tipLine = Payout::create(['show_id' => $natShow->id, 'streamer_id' => $nat->id, 'payout_type' => 'show_tips', 'calculated_payout' => 25,
            'gross_show_revenue' => 0, 'owner_fee_deducted' => 0, 'tips_included' => 25, 'status' => 'draft']);
        $this->assertTrue(PayRunSchedules::scopeToKind(Payout::query(), null)->whereKey($tipLine->id)->exists());
        $this->assertTrue(PayRunSchedules::scopeToKind(Payout::query(), 'tips')->whereKey($tipLine->id)->exists());
        $this->assertFalse(PayRunSchedules::scopeToKind(Payout::query(), 'wages')->whereKey($tipLine->id)->exists());
        $this->assertFalse(PayRunSchedules::scopeToKind(Payout::query(), 'share')->whereKey($tipLine->id)->exists());
    }

    public function test_ledger_charges_are_only_estill_streamers_and_each_sees_their_own(): void
    {
        $ed = $this->member('Estill Ed', $this->estill);
        $amy = $this->member('Estill Amy', $this->estill);
        $nat = $this->member('Natbat', $this->vcx);
        $edShow = $this->show($ed, $this->breaks, 300);
        $this->ledger('Shipping charge', -12.40, $ed, 'shipping', $edShow);
        $this->ledger('Order refund shipping charge', -5.10, $ed, 'shipping', $edShow);
        $this->ledger('Refund', -30, $ed, 'refunds', $edShow);
        $this->ledger('Adjustment', -7, $amy, 'correction');
        $this->ledger('Refund', -99, $nat, 'refunds'); // VCX: not charged
        $this->ledger('Refund', -55, $ed, 'refunds', null, 'pending'); // not approved
        $this->ledger('Tip', 20, $ed, 'tips'); // not a charge

        $sum = app(LedgerChargeReport::class)->summary(now()->subDays(7)->toDateString(), now()->toDateString());
        $this->assertSame(['Estill Ed', 'Estill Amy'], array_column($sum['people'], 'streamer'));
        $this->assertSame(-47.5, $sum['people'][0]['total']);
        $this->assertSame(-17.5, $sum['people'][0]['shipping']);
        $this->assertSame(-54.5, $sum['totals']['total']);
        $this->assertSame(4, $sum['entries']);

        // Admin: everyone, from the Ledger.
        $this->get(WhatnotLedgerResource::getUrl('charges'))->assertOk()->assertSee('Ledger charges');
        Livewire::test(LedgerCharges::class)->assertSee('Estill Ed')->assertSee('Estill Amy')->assertDontSee('Natbat')
            ->assertSee('Order refund shipping charge');

        // Each line ties back to its show, buyer and shipment.
        $orderNo = DB::table('whatnot_ledger_entries')->where('transaction_type', 'Refund')->where('amount', -30)->value('whatnot_order_id');
        $orderId = DB::table('whatnot_show_orders')->insertGetId(['show_id' => $edShow->id, 'whatnot_order_id' => $orderNo, 'buyer_username' => 'cardfan22',
            'item_name' => 'Prizm Hobby Box', 'quantity' => 1, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shipments')->insert(['show_id' => $edShow->id, 'whatnot_order_id' => $orderId, 'carrier' => 'usps', 'tracking_number' => '9400111899223',
            'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()]);
        Livewire::test(LedgerCharges::class)->assertSee('@cardfan22')->assertSee('Prizm Hobby Box')->assertSee('9400111899223')
            ->assertSee(\App\Filament\Resources\ShowResource::getUrl('view', ['record' => $edShow->id]), false);

        // A charged streamer sees only their own lines.
        $edUser = User::factory()->create();
        $ed->update(['user_id' => $edUser->id]);
        $this->actingAs($edUser);
        $this->assertTrue(MyLedgerCharges::canAccess());
        $this->get(MyLedgerCharges::getUrl())->assertOk();
        Livewire::test(LedgerCharges::class, ['lockedStreamerId' => $ed->id])
            ->assertSee('Your charges')->assertSee('Shipping charge')->assertSee('−$47.50')->assertDontSee('Estill Amy')->assertDontSee('−$7.00')
            ->set('streamer', $amy->id)->assertDontSee('−$7.00')->assertSee('−$47.50'); // the lock wins over any filter

        // VCX streamers are not charged, so they have no such page.
        $natUser = User::factory()->create();
        $nat->update(['user_id' => $natUser->id]);
        $this->actingAs($natUser);
        $this->assertFalse(MyLedgerCharges::canAccess());
    }

    public function test_pay_that_changed_after_it_was_locked_is_listed_and_admins_are_told_once(): void
    {
        $nat = $this->member('Natbat', $this->vcx);
        $show = $this->show($nat, $this->breaks, 1000, now()->subDays(3)->toDateString());
        app(\App\Services\PayoutService::class)->calculateForShow($show->fresh(['streamers', 'streamerLogEntry', 'channel']));
        $payout = Payout::query()->where('show_id', $show->id)->where('streamer_id', $nat->id)->firstOrFail();
        $run = WeeklyPayoutBatch::create(['business_id' => $this->vcx->id, 'week_start' => now()->subDays(6)->toDateString(), 'week_end' => now()->toDateString(),
            'status' => 'paid', 'finalized_at' => now()->subDay()]);
        DB::table('weekly_payout_batches')->where('id', $run->id)->update(['updated_at' => now()->subDay()]);
        $payout->update(['weekly_payout_batch_id' => $run->id, 'status' => 'paid']);
        DB::table('shows')->where('id', $show->id)->update(['updated_at' => now()->subDays(2)]);
        $paid = (float) $payout->fresh()->calculated_payout;

        // Nothing changed since it was paid.
        $this->assertCount(0, app(PayChangedAfterPayout::class)->rows());

        // Whatnot net is corrected upward after payday.
        $show->refresh()->trackChanges(['whatnot_net' => 1500], 'import');
        $show->update(['whatnot_net' => 1500]);
        $rows = app(PayChangedAfterPayout::class)->rows();
        $this->assertCount(1, $rows);
        $this->assertSame('Natbat', $rows[0]['streamer']);
        $this->assertSame($paid, $rows[0]['paid']);
        $this->assertGreaterThan($paid, $rows[0]['now']);
        $this->assertSame('whatnot_net', $rows[0]['changes'][0]['field']);

        \Illuminate\Support\Facades\Notification::fake();
        $this->artisan('payroll:changed-after-payout')->expectsOutputToContain('Changed after payout: 1')->assertSuccessful();
        $this->assertSame(1, app(PayChangedAfterPayout::class)->count());
        $this->assertSame('1', PayChangedSincePaid::getNavigationBadge());
        $this->artisan('payroll:changed-after-payout')->assertSuccessful(); // already told: no repeat
        $this->get(PayChangedSincePaid::getUrl())->assertOk()->assertSee($show->title)->assertSee('Whatnot net: 1000');
        Livewire::test(PayrollOverview::class)->assertSee('1 paid line changed after payout');

        // A line someone overrode by hand is theirs: not flagged.
        $payout->update(['calculation_notes' => 'Manual Pay Run override by Owner: $1 → $2. Reason: agreed']);
        $this->assertCount(0, app(PayChangedAfterPayout::class)->rows());
    }

    public function test_the_fulfillment_admin_sees_everything_but_business_profitability(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('fulfillment_admin', 'web');
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $fa = User::factory()->create();
        $fa->assignRole('fulfillment_admin');
        $nat = $this->member('Natbat', $this->vcx);
        $this->show($nat, $this->breaks, 400);
        $run = WeeklyPayoutBatch::create(['business_id' => $this->vcx->id, 'week_start' => now()->subDays(6)->toDateString(), 'week_end' => now()->toDateString(), 'status' => 'draft']);
        Payout::create(['show_id' => Show::query()->value('id'), 'streamer_id' => $nat->id, 'weekly_payout_batch_id' => $run->id, 'payout_type' => 'profit_share',
            'calculated_payout' => 40, 'gross_show_revenue' => 480, 'owner_fee_deducted' => 0, 'tips_included' => 0, 'status' => 'draft']);
        $this->actingAs($fa);

        $this->assertTrue($fa->isAdmin());
        $this->assertFalse($fa->canSeeBusinessFinancials());
        // Streaming info, payroll and show financials: yes.
        $this->assertTrue(PayrollOverview::canAccess());
        $this->assertTrue(\App\Filament\Pages\ReportsOverview::canAccess());
        $this->assertTrue(\App\Filament\Resources\ShowResource::canAccess());
        // How profitable the businesses are: no.
        $this->assertFalse(\App\Filament\Pages\ProfitLoss::canAccess());
        $this->get(\App\Filament\Pages\ProfitLoss::getUrl())->assertForbidden();
        $this->get(\App\Filament\Pages\ReportsOverview::getUrl())->assertOk()->assertDontSee('Finalized Business Profit');
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->getRouteKey()])->assertDontSee('Business account transfer');

        // An admin (or a fulfillment admin who is also an admin) sees them.
        $fa->assignRole('admin');
        $this->assertTrue($fa->fresh()->canSeeBusinessFinancials());
        $this->actingAs($this->owner);
        $this->assertTrue(\App\Filament\Pages\ProfitLoss::canAccess());
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->getRouteKey()])->assertSee('Business account transfer');
    }

    public function test_regular_admins_open_every_fulfillment_page_whatever_their_saved_role_list_says(): void
    {
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'reporting', 'fulfillment']));
        \App\Support\AdminModules::flushMemo();
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        // A saved admin page list from before the Fulfillment Center existed.
        \App\Support\NavVisibility::setVisibleForRole('admin', [PayrollOverview::class]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        foreach ([\App\Filament\Pages\FulfillmentHome::class, \App\Filament\Pages\FulfillmentStationBoard::class,
            \App\Filament\Pages\FulfillmentActivity::class, \App\Filament\Pages\FulfillmentReports::class] as $page) {
            $this->assertFalse(\App\Support\NavVisibility::isHiddenForUser($page, $admin), $page);
            $this->get($page::getUrl())->assertOk();
        }
        $this->assertTrue(\App\Filament\Resources\FulfillmentResource::canAccess());
    }
}
