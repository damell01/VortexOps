<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\NotificationSettings;
use App\Filament\Pages\ReportsOverview;
use App\Filament\Pages\WhatnotLosses;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Services\NotificationRouter;
use App\Services\ShowLossDigest;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Reporting workspaces: Whatnot losses from estimates, the notification panel, the overview, and the loss summary schedule. */
class ReportingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 23:00:00');
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_whatnot_loss_report_uses_estimates_without_requiring_payroll(): void
    {
        foreach ([['negative', -25], ['positive', 75], ['zero', 0]] as [$title, $net]) {
            Show::create(['title' => $title, 'show_date' => '2026-10-14', 'status' => 'draft', 'is_operational' => true, 'whatnot_net' => $net]);
        }
        Show::create(['title' => 'cancelled', 'show_date' => '2026-10-14', 'status' => 'cancelled', 'is_operational' => true, 'whatnot_net' => -80]);

        $rows = app(ShowLossDigest::class)->rows('whatnot_negative', Carbon::parse('2026-10-14'), Carbon::parse('2026-10-14'));
        $this->assertSame(['negative'], $rows->pluck('title')->all());
        Livewire::withQueryParams(['range' => 'this_week'])->test(WhatnotLosses::class)->assertSee('negative')->assertSee('25.00');
    }

    public function test_notification_panel_opens_and_schedule_validation_is_enforced(): void
    {
        Livewire::test(NotificationSettings::class)->call('openConfigure', 'whatnot_negative')
            ->assertSet('configure', 'whatnot_negative')->assertSee('Schedule')
            ->set('rules.whatnot_negative.schedule.time', '25:99')->call('saveConfigure')
            ->assertHasErrors(['rules.whatnot_negative.schedule.time']);

        Livewire::withQueryParams(['configure' => 'whatnot_negative'])->test(NotificationSettings::class)
            ->assertSet('configure', 'whatnot_negative')->assertSee('Skip empty summaries');
    }

    public function test_report_overview_renders_and_does_not_treat_missing_costs_as_final_profit(): void
    {
        Show::create(['title' => 'Incomplete', 'show_date' => '2026-10-14', 'status' => 'draft', 'is_operational' => true, 'whatnot_net' => 100]);

        Livewire::test(ReportsOverview::class)->assertSee('Report Library')->assertSee('Pending Financials')
            ->assertSee('0 finalized shows only');
    }

    public function test_empty_loss_summary_does_not_notify_anyone(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-16 17:05', 'America/Chicago'));

        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_scheduled_summary_respects_time_and_does_not_repeat(): void
    {
        Notification::fake();
        Show::create(['title' => 'Loss', 'show_date' => '2026-10-14', 'status' => 'draft', 'is_operational' => true, 'whatnot_net' => -20]);
        app(NotificationRouter::class)->saveRules(['whatnot_negative' => ['enabled' => true, 'roles' => ['owner'], 'users' => [], 'emails' => [], 'in_app' => true, 'email' => false,
            'schedule' => ['mode' => 'daily', 'time' => '18:00', 'skip_empty' => true]]]);

        Carbon::setTestNow(Carbon::parse('2026-10-15 17:59', 'America/Chicago'));
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-10-15 18:01', 'America/Chicago'));
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        $this->artisan('reports:show-loss-notifications')->assertSuccessful();
        Notification::assertCount(1);
    }

    public function test_settings_saved_on_the_old_daily_and_weekly_events_carry_over(): void
    {
        Setting::set('notification_rules', json_encode(['weekly_whatnot_losses' => ['enabled' => true, 'roles' => ['owner'], 'users' => [], 'emails' => ['boss@example.com'], 'in_app' => true, 'email' => true]]));
        Setting::set('whatnot_loss_schedules', json_encode(['weekly_whatnot_losses' => ['time' => '16:30', 'day' => 0]]));

        (require database_path('migrations/2026_10_14_230000_merge_whatnot_loss_summary_settings.php'))->up();
        $rule = (new NotificationRouter)->rule('whatnot_negative');

        $this->assertSame(['owner'], $rule['roles']);
        $this->assertSame(['boss@example.com'], $rule['emails']);
        $this->assertSame(['weekly', 7, '16:30'], [$rule['schedule']['mode'], $rule['schedule']['day'], $rule['schedule']['time']]);
        $this->assertArrayNotHasKey('weekly_whatnot_losses', json_decode(Setting::get('notification_rules'), true));
    }
}
