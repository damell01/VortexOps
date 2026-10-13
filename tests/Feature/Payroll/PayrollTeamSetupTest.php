<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\PaymentStructures;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Support\PaySchedule;
use App\Support\PaymentStructure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Payroll setup from the team's notes: in-house streamers paid hourly on top
 * of their share, a configurable pay week start day, old profiles removable
 * from the roster, and pay set from the user's own profile.
 */
class PayrollTeamSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        $this->actingAs(User::factory()->create(['email' => 'owner@test.com']));
        Setting::set('payroll_burden_per_shipment', '0');
        Setting::set('payroll_burden_per_hour', '0');
    }

    private function showFor(Streamer $streamer, float $hours = 3): Payout
    {
        $channel = WhatnotChannel::firstOrCreate(['name' => 'Breaks'], ['status' => 'active']);
        $show = Show::create([
            'whatnot_channel_id' => $channel->id, 'title' => 'Night break', 'show_date' => today()->toDateString(),
            'gross_revenue' => 1000, 'whatnot_net' => 900, 'tips' => 0, 'show_duration' => (int) ($hours * 60), 'status' => 'reconciled',
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved',
            'product_cost' => 400, 'hours_streamed' => $hours, 'number_of_shipments' => 0,
        ]);

        return app(PayoutService::class)->calculateForShow($show->fresh(['streamers']))[0];
    }

    private function streamer(array $attributes = []): Streamer
    {
        return Streamer::create(array_merge([
            'name' => 'Jordan', 'status' => 'active', 'member_type' => 'streamer', 'streamer_type' => 'in_house',
            'payout_type' => 'profit_share', 'payout_percentage' => 10, 'include_tips' => false,
        ], $attributes));
    }

    public function test_in_house_streamers_get_hourly_on_top_of_their_share_and_remote_do_not(): void
    {
        PaymentStructure::saveDefaults('streamer', ['payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 10, 'include_tips' => false, 'hourly_rate' => 15]);

        $inHouse = $this->streamer();
        PaymentStructure::adoptDefaults($inHouse);
        $payout = $this->showFor($inHouse->fresh(), 3);
        // Share: (1000 − 400) × 10% = 60; hourly: 3 × 15 = 45.
        $this->assertEqualsWithDelta(60.00, (float) $payout->calculated_payout, 0.001);
        $line = \App\Models\Payout::where('show_id', $payout->show_id)->where('payout_type', 'show_hours')->sole();
        $this->assertEqualsWithDelta(45.00, (float) $line->calculated_payout, 0.001);

        // A profile still on its old columns keeps paying exactly as before,
        // even if a stale hourly rate sits in them.
        $legacy = $this->streamer(['name' => 'Old Columns', 'hourly_rate' => 1]);
        $this->assertEqualsWithDelta(60.00, (float) $this->showFor($legacy->fresh(), 3)->calculated_payout, 0.001);

        $remote = $this->streamer(['name' => 'Remy', 'streamer_type' => 'remote']);
        PaymentStructure::adoptDefaults($remote);
        $this->assertEqualsWithDelta(60.00, (float) $this->showFor($remote->fresh(), 3)->calculated_payout, 0.001);
    }

    public function test_pay_week_start_day_sets_new_runs_and_bridges_the_change_without_gaps(): void
    {
        Carbon::setTestNow('2026-10-14 12:00'); // a Wednesday
        $this->assertSame(['2026-10-12', '2026-10-18'], array_map(fn ($d) => $d->toDateString(), PaySchedule::periodFor(now())));

        WeeklyPayoutBatch::create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'draft']);
        Setting::set(PaySchedule::SETTING, (string) Carbon::SATURDAY);

        // The existing run keeps its dates; the next starts the day after it
        // ends and runs to the new week's end, then regular Saturday weeks.
        $this->assertSame(['2026-10-12', '2026-10-18'], array_map(fn ($d) => $d->toDateString(), PaySchedule::periodFor('2026-10-15')));
        $this->assertSame(['2026-10-19', '2026-10-23'], array_map(fn ($d) => $d->toDateString(), PaySchedule::periodFor('2026-10-20')));
        $this->assertSame(['2026-10-24', '2026-10-30'], array_map(fn ($d) => $d->toDateString(), PaySchedule::periodFor('2026-10-26')));

        $periods = PaySchedule::periodsBetween('2026-10-12', '2026-11-05');
        for ($i = 1; $i < count($periods); $i++) {
            $this->assertTrue($periods[$i - 1][1]->copy()->addDay()->equalTo($periods[$i][0]), 'periods are back to back');
        }

        Carbon::setTestNow('2026-10-20 09:00');
        $result = app(\App\Services\PayRunAutomationService::class)->syncWeek(now(), false);
        $this->assertSame('2026-10-19', $result['batch']->week_start->toDateString());
        $this->assertSame('2026-10-23', $result['batch']->week_end->toDateString());
        Carbon::setTestNow();
    }

    public function test_removing_a_profile_deletes_it_without_history_and_archives_it_with_history(): void
    {
        $unused = $this->streamer(['name' => 'Never Streamed']);
        $used = $this->streamer(['name' => 'Has History']);
        $this->showFor($used);

        $page = Livewire::test(PaymentStructures::class)
            ->assertSee('Never Streamed')->assertSee('Has History')
            ->call('removeMember', $unused->id)
            ->call('removeMember', $used->id);

        $this->assertNull(Streamer::find($unused->id));
        $this->assertSame('inactive', $used->fresh()->status);
        $page->assertDontSee('Has History')
            ->call('setShowArchived', true)->assertSee('Has History')
            ->call('restoreMember', $used->id);
        $this->assertSame('active', $used->fresh()->status);
    }

    public function test_deleting_a_user_takes_their_profile_off_payroll(): void
    {
        $user = User::factory()->create();
        $profile = $this->streamer(['user_id' => $user->id, 'name' => 'Leaver']);
        $user->delete();

        $this->assertNull(Streamer::find($profile->id));
    }
}
