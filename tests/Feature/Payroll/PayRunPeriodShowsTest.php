<?php

namespace Tests\Feature\Payroll;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Models\{Business, Payout, Show, Streamer, User, WeeklyPayoutBatch, WhatnotChannel};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayRunPeriodShowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_period_shows_without_reports_are_visible_without_creating_pay(): void
    {
        $this->enableAdminModules();
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner);
        $business = Business::create(['name' => 'Period Business']);
        $other = Business::create(['name' => 'Different Business']);
        $person = Streamer::create(['name' => 'Period Host', 'business_id' => $business->id, 'status' => 'active']);
        $outsider = Streamer::create(['name' => 'Other Host', 'business_id' => $other->id, 'status' => 'active']);
        $channel = WhatnotChannel::create(['name' => 'Period Channel', 'status' => 'active']);
        foreach ([['Opening boundary', '2026-10-20', $person], ['Closing boundary', '2026-10-26', $person], ['Before period', '2026-10-19', $person], ['After period', '2026-10-27', $person], ['Other business show', '2026-10-22', $outsider]] as [$title, $date, $host]) {
            $show = Show::create(['title' => $title, 'show_date' => $date, 'whatnot_channel_id' => $channel->id, 'created_by' => $owner->id, 'status' => 'scheduled']);
            $show->streamers()->attach($host);
        }
        $run = WeeklyPayoutBatch::create(['business_id' => $business->id, 'week_start' => '2026-10-20', 'week_end' => '2026-10-26', 'status' => 'draft', 'created_by' => $owner->id]);
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->id])
            ->assertSee('Shows in this period')->assertSee('Opening boundary')->assertSee('Closing boundary')
            ->assertSee('Period Host')->assertSee('Needs report')->assertSee('No pay lines yet')
            ->assertDontSee('Before period')->assertDontSee('After period')->assertDontSee('Other business show')
            ->assertDontSee('Ready to finalize');
        $this->assertSame(0, Payout::count());
        $run->update(['status' => 'finalized']);
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->id])->assertDontSee('Shows in this period');
    }
}
