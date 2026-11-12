<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\ReportCompare;
use App\Filament\Pages\Reports;
use App\Models\Goal;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\GoalProgress;
use App\Support\ReportCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** The report frame, Compare and goal pace. */
class ReportStudioTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $main;
    private WhatnotChannel $cards;
    private Streamer $ana;
    private Streamer $ben;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 18:00:00');
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'reporting', 'inventory']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $this->main = WhatnotChannel::create(['name' => 'Main', 'whatnot_username' => 'main', 'channel_url' => 'https://www.whatnot.com/user/main', 'status' => 'active']);
        $this->cards = WhatnotChannel::create(['name' => 'Cards', 'whatnot_username' => 'cards', 'channel_url' => 'https://www.whatnot.com/user/cards', 'status' => 'active']);
        $this->ana = Streamer::create(['name' => 'Ana', 'status' => 'active']);
        $this->ben = Streamer::create(['name' => 'Ben', 'status' => 'active']);

        $this->show('Ana 1', '2026-11-03', $this->main, $this->ana, 1000, 900, 120);
        $this->show('Ana 2', '2026-11-10', $this->main, $this->ana, 2000, 1800, 120);
        $this->show('Ben 1', '2026-11-12', $this->cards, $this->ben, 500, 450, 60);
        $this->show('Old', '2026-09-01', $this->cards, $this->ben, 700, 600, 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $title, string $date, WhatnotChannel $ch, Streamer $st, float $gross, float $net, int $minutes): Show
    {
        $show = Show::create(['title' => $title, 'show_date' => $date, 'status' => 'reconciled', 'is_operational' => true,
            'whatnot_channel_id' => $ch->id, 'gross_revenue' => $gross, 'whatnot_net' => $net, 'show_duration' => $minutes]);
        $show->streamers()->attach($st->id, ['is_primary' => true]);

        return $show;
    }

    public function test_compare_streamers_side_by_side(): void
    {
        $page = Livewire::test(ReportCompare::class, ['kind' => 'streamer', 'items' => $this->ana->id.','.$this->ben->id, 'range' => '30']);
        $page->assertOk()->assertSee('Head to head')->assertSee('Ana earned');

        $c = $page->instance()->comparison();
        $this->assertSame(2700.0, $c['items'][0]['f']['net']);
        $this->assertSame(2, $c['items'][0]['f']['shows']);
        $this->assertSame(450.0, $c['items'][1]['f']['net']);
        $this->assertSame(0, $c['best']['net']);
        // Running total ends at the full amount.
        $this->assertSame(2700.0, end($c['race']['lines'][0]));
    }

    public function test_compare_channels_periods_and_shows(): void
    {
        $c = Livewire::test(ReportCompare::class, ['kind' => 'channel', 'items' => $this->main->id.','.$this->cards->id])->instance()->comparison();
        $this->assertSame([2700.0, 450.0], [$c['items'][0]['f']['net'], $c['items'][1]['f']['net']]);

        $c = Livewire::test(ReportCompare::class, ['kind' => 'period', 'items' => 'tm,lm'])->instance()->comparison();
        $this->assertSame('This month', $c['items'][0]['name']);
        $this->assertSame(3, $c['items'][0]['f']['shows']);

        $ids = Show::query()->whereIn('title', ['Ana 2', 'Ben 1'])->orderBy('title')->pluck('id')->all();
        $c = Livewire::test(ReportCompare::class, ['kind' => 'show', 'items' => implode(',', $ids)])->instance()->comparison();
        $this->assertSame('Ana 2', $c['items'][0]['name']);
        $this->assertSame([], $c['race']);
    }

    public function test_picks_are_validated_and_filled(): void
    {
        $page = Livewire::test(ReportCompare::class, ['kind' => 'nonsense', 'items' => '999999,abc']);
        $this->assertSame('streamer', $page->get('kind'));
        $this->assertCount(2, $page->instance()->ids());

        $page->call('addItem')->call('swap')->call('removeItem', 0);
        $this->assertCount(2, $page->instance()->ids());
        $page->call('setKind', 'period');
        $this->assertSame(['l30', 'p30'], $page->instance()->ids());
    }

    public function test_goal_pace_projects_the_month(): void
    {
        Goal::create(['month' => '2026-11-01', 'scope' => 'all', 'metric' => 'whatnot_net', 'target' => 6000]);
        $pace = app(GoalProgress::class)->pace('whatnot_net');
        // 3150 in after 15 of 30 days → on pace for 6300.
        $this->assertSame(3150.0, $pace['actual']);
        $this->assertSame(6300.0, $pace['projected']);
        $this->assertSame('ahead', $pace['status']);
        $this->assertSame(200.0, $pace['per_day']);
        $this->assertNull(app(GoalProgress::class)->pace('gross'));

        Livewire::test(Reports::class)->set('weekMetric', 'net')->assertSee('On pace for $6,300 this month')->assertSee('Goal / week');
    }

    public function test_report_frame_lists_reports_and_compare_buttons(): void
    {
        $groups = collect(ReportCatalog::forUser())->pluck('group')->all();
        $this->assertContains('Overview', $groups);
        $this->assertContains('Sales', $groups);

        $this->get(Reports::getUrl())->assertOk()->assertSee('vxrs-lib', false)->assertSee('Compare')->assertSee('vxrs-add', false);
        $this->get(ReportCompare::getUrl())->assertOk();
    }
}
