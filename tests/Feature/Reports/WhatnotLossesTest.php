<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\ReportsOverview;
use App\Filament\Pages\WhatnotLosses;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\ShowLossSummaryNotification;
use App\Services\NotificationRouter;
use App\Support\NotificationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Shows with negative estimated Whatnot earnings: the report, its summary, and the schedule. */
class WhatnotLossesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 13:00:00'); // a Wednesday
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com', 'name' => 'Owner']);
        Setting::set('enabled_admin_modules', json_encode(array_keys(\App\Support\AdminModules::definitions())));
        \App\Support\AdminModules::flushMemo();
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $jake = Streamer::create(['name' => 'Jake', 'status' => 'active']);
        foreach ([['Friday Break', '2026-10-09', -120], ['Sunday Singles', '2026-10-11', -35], ['Good Night', '2026-10-10', 300]] as [$title, $date, $net]) {
            $show = Show::create([
                'whatnot_channel_id' => $channel->id, 'title' => $title, 'show_date' => $date, 'gross_revenue' => 100,
                'whatnot_net' => $net, 'tips' => 0, 'status' => 'reconciled', 'is_operational' => true, 'created_by' => $this->owner->id,
            ]);
            $show->streamers()->attach($jake->id, ['is_primary' => true]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_page_lists_only_negative_shows_for_last_week(): void
    {
        $this->actingAs($this->owner);

        $this->get(WhatnotLosses::getUrl())->assertOk()
            ->assertSee('Whatnot Losses')->assertSee('Friday Break')->assertSee('Sunday Singles')->assertDontSee('Good Night')
            ->assertSee('-$155.00')->assertSee('Oct 5 – 11, 2026');
    }

    public function test_send_summary_goes_to_the_picked_people_in_app(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => 'someone@example.com']);
        $this->actingAs($this->owner);

        Livewire::test(WhatnotLosses::class)
            ->call('openSend')->assertSet('sendOpen', true)
            ->set('sendTo', 'me')->set('sendEmail', false)->set('sendInApp', true)
            ->call('sendNow')->assertHasNoErrors()->assertSet('sendOpen', false);

        Notification::assertSentTo($this->owner, ShowLossSummaryNotification::class, function ($n, $channels) {
            return $channels === ['database'] && $n->digest['count'] === 2 && $n->digest['total'] === -155.0;
        });
        Notification::assertNotSentTo($admin, ShowLossSummaryNotification::class);
    }

    public function test_the_weekly_summary_goes_once_after_its_time(): void
    {
        Notification::fake();
        // Default: weekly, Friday 5pm — covering last Monday–Sunday.
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-10-16 17:05', 'America/Chicago'));
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertSentTo($this->owner, ShowLossSummaryNotification::class, fn ($n) => $n->digest['count'] === 2 && $n->digest['label'] === 'Oct 5 – 11, 2026');

        Notification::fake();
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_schedule_settings_round_trip_and_describe(): void
    {
        app(NotificationRouter::class)->saveRules(['whatnot_negative' => ['enabled' => true, 'roles' => ['owner'], 'users' => [], 'emails' => [], 'in_app' => true, 'email' => false,
            'schedule' => ['mode' => 'daily', 'time' => '07:30', 'day' => 3, 'skip_empty' => false]]]);
        $s = app(NotificationRouter::class)->rule('whatnot_negative')['schedule'];

        $this->assertSame('daily', $s['mode']);
        $this->assertSame('Daily · 7:30 AM', NotificationSchedule::describe($s));
        $this->assertFalse($s['skip_empty']);
        $this->assertNull(app(NotificationRouter::class)->rule('low_stock')['schedule']);
    }

    public function test_reports_overview_shows_what_needs_attention(): void
    {
        $this->actingAs($this->owner);

        $this->get(ReportsOverview::getUrl(['range' => 'last_week']))->assertOk()
            ->assertSee('Reports Overview')->assertSee('Needs Attention')->assertSee('Negative Whatnot Earnings')
            ->assertSee('2 shows')->assertSee('-$155')->assertSee('Report Library')->assertSee('Whatnot Losses');
    }
}
