<?php

namespace Tests\Feature\Businesses;

use App\Filament\Pages\Businesses;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayRunAutomationService;
use App\Services\PayoutService;
use App\Support\BusinessContext;
use App\Support\PaymentStructure;
use App\Support\UserPayFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vortex Collects and Estill Distribution share channels but not people:
 * each person belongs to one business, each business runs its own payroll,
 * and Estill's streamers are paid a sales split rather than profit share.
 */
class BusinessPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private WhatnotChannel $channel;
    private Business $vcx;
    private Business $estill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $this->vcx = Business::where('short_name', 'VCX')->firstOrFail();
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 10,
            'include_tips' => false, 'hourly_rate' => 15,
        ]);
        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'sales_split', 'payout_cadence' => 'weekly', 'house_cut_percentage' => 20,
            'sales_split_basis' => 'net', 'include_tips' => false,
        ], $this->estill->id);
    }

    private function member(string $name, ?Business $business, array $extra = []): Streamer
    {
        return Streamer::create($extra + [
            'name' => $name,
            'member_type' => 'streamer',
            'streamer_type' => 'in_house',
            'status' => 'active',
            'business_id' => $business?->id,
            'compensation_override_fields' => [],
        ]);
    }

    private function show(Streamer $streamer, ?string $date = null): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title' => "Show with {$streamer->name}",
            'show_date' => $date ?? now()->toDateString(),
            'gross_revenue' => 1200,
            'whatnot_net' => 1000,
            'tips' => 0,
            'units_sold' => 10,
            'show_duration' => 120,
            'status' => 'reconciled',
            'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return $show;
    }

    public function test_the_two_businesses_are_seeded(): void
    {
        $this->assertSame(['Vortex Collects', 'Estill Distribution'], Business::ordered()->pluck('name')->all());
    }

    public function test_an_estill_streamer_keeps_the_rest_after_the_business_cut(): void
    {
        $streamer = $this->member('Estill Streamer', $this->estill);
        $show = $this->show($streamer);

        [$payout] = app(PayoutService::class)->calculateForShow($show->load('streamers'));

        $this->assertSame('sales_split', $payout->payout_type);
        // $1,000 net, the business keeps 20%.
        $this->assertEqualsWithDelta(800.00, (float) $payout->calculated_payout, 0.001);
        $this->assertStringContainsString('20% kept by the business', $payout->calculation_notes);
    }

    public function test_the_split_can_be_taken_from_gross_sales(): void
    {
        $streamer = $this->member('Gross Splitter', $this->estill);
        PaymentStructure::saveOverrides($streamer, ['sales_split_basis' => 'gross']);
        $show = $this->show($streamer->fresh());

        [$payout] = app(PayoutService::class)->calculateForShow($show->load('streamers'));

        $this->assertEqualsWithDelta(960.00, (float) $payout->calculated_payout, 0.001);
    }

    public function test_estills_hourly_streamer_is_paid_like_a_vcx_streamer(): void
    {
        $hourly = $this->member('Estill Hourly', $this->estill);
        PaymentStructure::saveOverrides($hourly, ['payout_type' => 'profit_share', 'payout_percentage' => 10, 'hourly_rate' => 20]);
        $hourly->refresh();

        $this->assertSame('profit_share', $hourly->payout_type);
        $this->assertSame(20.0, $hourly->paidHourlyRate());

        // Hourly pay is for Hours Worked from the report, never the show's length.
        $show = $this->show($hourly);
        \App\Models\StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $hourly->id, 'hours_streamed' => 2]);
        [$payout] = app(PayoutService::class)->calculateForShow($show->load('streamers'));
        $line = \App\Models\Payout::where('show_id', $show->id)->where('payout_type', 'show_hours')->sole();
        $this->assertEquals(40, (float) $line->calculated_payout);
        $this->assertStringNotContainsString('hourly', $payout->calculation_notes);
    }

    public function test_a_vcx_streamer_keeps_the_shared_profit_share(): void
    {
        $vcx = $this->member('VCX Streamer', $this->vcx);

        $this->assertSame('profit_share', $vcx->payout_type);
        $this->assertSame(15.0, $vcx->paidHourlyRate());
    }

    public function test_moving_someone_to_estill_changes_how_they_are_paid(): void
    {
        $member = $this->member('Mover', $this->vcx);
        $this->assertSame('profit_share', $member->payout_type);

        $member->update(['business_id' => $this->estill->id]);

        $this->assertSame('sales_split', $member->fresh()->payout_type);
        $this->assertNull($member->fresh()->paidHourlyRate());
    }

    public function test_choosing_a_business_scopes_people_shows_and_pay(): void
    {
        $vcx = $this->member('VCX Person', $this->vcx);
        $estill = $this->member('Estill Person', $this->estill);
        $vcxShow = $this->show($vcx);
        $estillShow = $this->show($estill);
        $unassigned = Show::create([
            'whatnot_channel_id' => $this->channel->id, 'title' => 'Nobody yet', 'show_date' => now()->toDateString(),
            'status' => 'reconciled', 'created_by' => $this->owner->id,
        ]);
        Payout::create(['show_id' => $vcxShow->id, 'streamer_id' => $vcx->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'draft']);
        Payout::create(['show_id' => $estillShow->id, 'streamer_id' => $estill->id, 'payout_type' => 'sales_split', 'calculated_payout' => 20, 'status' => 'draft']);

        BusinessContext::setActive($this->estill->id);

        // Payroll pages opt in...
        $this->assertSame(['Estill Person'], Streamer::query()->inBusinessContext()->pluck('name')->all());
        $this->assertEqualsCanonicalizing([$estillShow->id, $unassigned->id], Show::query()->inBusinessContext()->pluck('id')->all());
        $this->assertSame([$estill->id], Payout::query()->inBusinessContext()->pluck('streamer_id')->all());

        // ...everything else keeps showing every business.
        $this->assertCount(2, Streamer::query()->inChannelContext()->get());
        $this->assertCount(3, Show::query()->inChannelContext()->get());
        $this->assertCount(2, Payout::query()->inChannelContext()->get());
    }

    public function test_each_business_gets_its_own_pay_run_for_the_same_week(): void
    {
        $vcx = $this->member('VCX Person', $this->vcx);
        $estill = $this->member('Estill Person', $this->estill);
        $vcxPay = Payout::create(['show_id' => $this->show($vcx)->id, 'streamer_id' => $vcx->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'draft']);
        $estillPay = Payout::create(['show_id' => $this->show($estill)->id, 'streamer_id' => $estill->id, 'payout_type' => 'sales_split', 'calculated_payout' => 20, 'status' => 'draft']);

        $service = app(PayoutService::class);
        $estillRun = $service->createWeeklyBatch(now()->toDateString(), $this->estill->id);
        $vcxRun = $service->createWeeklyBatch(now()->toDateString(), $this->vcx->id);

        $this->assertSame($estillRun->id, $estillPay->fresh()->weekly_payout_batch_id);
        $this->assertSame($vcxRun->id, $vcxPay->fresh()->weekly_payout_batch_id);

        // Paying Estill's run leaves Vortex Collects' people alone.
        $service->finalizeBatch($estillRun, force: true);
        $service->markBatchPaid($estillRun->fresh());
        $this->assertSame('paid', $estillPay->fresh()->status);
        $this->assertSame('draft', $vcxPay->fresh()->status);

        // A second run for the same business and week is still refused.
        $this->expectException(\RuntimeException::class);
        $service->createWeeklyBatch(now()->toDateString(), $this->estill->id);
    }

    public function test_a_run_for_everyone_still_clashes_with_a_business_run(): void
    {
        app(PayoutService::class)->createWeeklyBatch(now()->toDateString(), $this->estill->id);

        $this->expectException(\RuntimeException::class);
        app(PayoutService::class)->createWeeklyBatch(now()->toDateString());
    }

    public function test_an_old_draft_run_splits_into_one_run_per_business(): void
    {
        $vcx = $this->member('VCX Person', $this->vcx);
        $estill = $this->member('Estill Person', $this->estill);
        $nobody = $this->member('No Business', null);
        $vcxPay = Payout::create(['show_id' => $this->show($vcx)->id, 'streamer_id' => $vcx->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'draft']);
        $estillPay = Payout::create(['show_id' => $this->show($estill)->id, 'streamer_id' => $estill->id, 'payout_type' => 'sales_split', 'calculated_payout' => 20, 'status' => 'draft']);
        $nobodyPay = Payout::create(['show_id' => $this->show($nobody)->id, 'streamer_id' => $nobody->id, 'payout_type' => 'profit_share', 'calculated_payout' => 5, 'status' => 'draft']);
        $legacy = app(PayoutService::class)->createWeeklyBatch(now()->toDateString());
        $this->assertNull($legacy->business_id);

        $result = app(PayRunAutomationService::class)->splitByBusiness($legacy, $this->estill->id);

        $this->assertCount(2, $result['runs']);
        $this->assertSame(['No Business'], $result['unassigned']);
        $legacy->refresh();
        $this->assertSame($this->estill->id, $legacy->business_id);
        $this->assertSame($legacy->id, $estillPay->fresh()->weekly_payout_batch_id);
        $this->assertEqualsWithDelta(20.0, (float) $legacy->total_payout, 0.001);

        $vcxRun = WeeklyPayoutBatch::where('business_id', $this->vcx->id)->firstOrFail();
        $this->assertSame($vcxRun->id, $vcxPay->fresh()->weekly_payout_batch_id);
        $this->assertSame($legacy->week_start->toDateString(), $vcxRun->week_start->toDateString());
        $this->assertNull($nobodyPay->fresh()->weekly_payout_batch_id);
    }

    public function test_the_board_moves_people_between_businesses(): void
    {
        $a = $this->member('Alpha', null);
        $b = $this->member('Bravo', null);
        $c = $this->member('Charlie', $this->vcx);

        Livewire::test(Businesses::class)
            ->assertSee('Not assigned')
            ->assertSee('Estill Distribution')
            ->call('assign', [$a->id], $this->estill->id)
            ->set('selected', [$b->id, $c->id])
            ->call('moveSelected', $this->estill->id)
            ->assertSet('selected', []);

        $this->assertSame([$this->estill->id], Streamer::whereKey([$a->id, $b->id, $c->id])->distinct()->pluck('business_id')->all());

        Livewire::test(Businesses::class)->call('assign', [$c->id], null);
        $this->assertNull($c->fresh()->business_id);
    }

    public function test_the_board_adds_and_refuses_to_delete_a_business_in_use(): void
    {
        $this->member('Someone', $this->estill);

        Livewire::test(Businesses::class)
            ->call('newBusiness')
            ->set('businessForm.name', 'Third Co')
            ->set('businessForm.color', '#123456')
            ->call('saveBusiness')
            ->call('deleteBusiness', $this->estill->id);

        $this->assertTrue(Business::where('name', 'Third Co')->exists());
        $this->assertTrue(Business::whereKey($this->estill->id)->exists());
    }

    public function test_the_user_pay_section_sets_the_business_without_pinning_the_pay_type(): void
    {
        $member = $this->member('Profile', $this->vcx);

        UserPayFields::apply($member, [UserPayFields::BUSINESS => $this->estill->id, 'pay_payout_percentage' => null, 'pay_house_cut_percentage' => 25, 'pay_hourly_rate' => null]);
        $member->refresh();

        $this->assertSame($this->estill->id, $member->business_id);
        $this->assertNotContains('payout_type', $member->compensation_override_fields);
        $this->assertSame('sales_split', $member->payout_type);
        $this->assertEqualsWithDelta(25.0, (float) $member->house_cut_percentage, 0.001);

        UserPayFields::apply($member, [UserPayFields::MODEL => 'profit_share', 'pay_house_cut_percentage' => 25]);
        $this->assertSame('profit_share', $member->fresh()->payout_type);
    }

    public function test_the_switcher_and_banner_show_the_chosen_business(): void
    {
        BusinessContext::setActive($this->estill->id);

        $this->get(\App\Filament\Pages\PayrollOverview::getUrl())
            ->assertOk()
            ->assertSee('vx-business-banner', false)
            ->assertSee('aria-label="Business: Estill Distribution"', false)
            ->assertSee('Estill Distribution');

        // Not on other pages: the picker only means something for payroll.
        $this->get(\App\Filament\Pages\DashboardImproved::getUrl())
            ->assertOk()
            ->assertDontSee('vx-business-banner', false)
            ->assertDontSee('aria-label="Business:', false);
    }

    public function test_fulfillment_admins_can_open_the_businesses_page(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('fulfillment_admin', 'web');
        $fulfillmentAdmin = User::factory()->create();
        $fulfillmentAdmin->assignRole('fulfillment_admin');
        $this->actingAs($fulfillmentAdmin);

        $this->assertTrue(Businesses::canAccess());
        $this->get(Businesses::getUrl())->assertOk()->assertSee('Estill Distribution');
    }

    public function test_admins_on_an_allow_list_are_granted_the_businesses_page(): void
    {
        \App\Models\Setting::set('role_visible_nav', json_encode(['admin' => [\App\Filament\Pages\PayrollOverview::class]]));
        \App\Support\NavVisibility::flushMemo();
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertTrue(\App\Support\NavVisibility::isHiddenForUser(Businesses::class, $admin));

        (require database_path('migrations/2026_10_13_200000_grant_businesses_page_to_admins.php'))->up();
        \App\Support\NavVisibility::flushMemo();

        $this->assertFalse(\App\Support\NavVisibility::isHiddenForUser(Businesses::class, $admin));
        $this->actingAs($admin)->get(Businesses::getUrl())->assertOk();
    }

    public function test_the_owner_menu_links_to_roles_and_permissions(): void
    {
        $this->get(\App\Filament\Pages\DashboardImproved::getUrl())
            ->assertOk()
            ->assertSee(\App\Filament\Resources\RoleResource::getUrl('index'), false);
    }
}
