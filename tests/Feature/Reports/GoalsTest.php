<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\Goals;
use App\Filament\Widgets\GoalsProgressWidget;
use App\Models\Business;
use App\Models\Goal;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\GoalProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Monthly goals per business or channel, with progress from that month's shows. */
class GoalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 12:00:00');
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'reporting']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_progress_counts_the_months_shows_by_channel_and_business(): void
    {
        $main = WhatnotChannel::create(['name' => 'Main', 'whatnot_username' => 'main', 'channel_url' => 'https://www.whatnot.com/user/main', 'status' => 'active']);
        $biz = Business::create(['name' => 'Vortex']);
        $ana = Streamer::create(['name' => 'Ana', 'status' => 'active', 'business_id' => $biz->id]);

        $a = Show::create(['title' => 'A', 'show_date' => '2026-11-03', 'status' => 'reconciled', 'whatnot_channel_id' => $main->id, 'whatnot_net' => 600, 'first_time_buyers' => 4]);
        $a->streamers()->attach($ana->id);
        Show::create(['title' => 'B', 'show_date' => '2026-11-10', 'status' => 'reconciled', 'whatnot_net' => 400]);
        Show::create(['title' => 'Cancelled', 'show_date' => '2026-11-11', 'status' => 'cancelled', 'whatnot_net' => 999]);
        Show::create(['title' => 'Last month', 'show_date' => '2026-10-30', 'status' => 'reconciled', 'whatnot_net' => 999]);

        Goal::create(['month' => '2026-11-01', 'scope' => 'all', 'metric' => 'whatnot_net', 'target' => 2000]);
        Goal::create(['month' => '2026-11-01', 'scope' => 'channel', 'scope_id' => $main->id, 'metric' => 'whatnot_net', 'target' => 500]);
        Goal::create(['month' => '2026-11-01', 'scope' => 'business', 'scope_id' => $biz->id, 'metric' => 'new_buyers', 'target' => 40]);

        $rows = app(GoalProgress::class)->forMonth('2026-11-01')->keyBy(fn ($r) => $r['scope'] . '/' . $r['metric']);

        $this->assertSame(1000.0, $rows['Everyone/whatnot_net']['actual']);
        $this->assertSame('on_track', $rows['Everyone/whatnot_net']['status']);   // 50% at day 15 of 30
        $this->assertSame('done', $rows['Main/whatnot_net']['status']);
        $this->assertSame(4.0, $rows['Vortex/new_buyers']['actual']);
        $this->assertSame('behind', $rows['Vortex/new_buyers']['status']);
    }

    public function test_goals_are_added_copied_and_shown_on_the_dashboard(): void
    {
        Goal::create(['month' => '2026-10-01', 'scope' => 'all', 'metric' => 'shows', 'target' => 30]);

        Livewire::test(Goals::class)
            ->set('metric', 'whatnot_net')->set('target', '$20,000')->call('save')->assertHasNoErrors()
            ->call('copyPrevious')
            ->assertSee('Shows streamed')->assertSee('Whatnot net');

        $this->assertSame(2, Goal::query()->whereDate('month', '2026-11-01')->count());
        $this->assertSame('20000.00', (string) Goal::query()->where('metric', 'whatnot_net')->value('target'));

        Livewire::test(Goals::class)->set('scope', 'channel')->set('target', '10')->call('save')->assertHasErrors(['scopeId']);

        Livewire::test(GoalsProgressWidget::class)->assertSee('November goals')->assertSee('$20,000');
    }
}
