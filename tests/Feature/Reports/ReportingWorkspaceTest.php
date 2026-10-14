<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\NotificationSettings;
use App\Filament\Pages\ReportsOverview;
use App\Filament\Pages\WhatnotLosses;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Services\WhatnotLossReport;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 23:00:00');
        Cache::flush();
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email'=>'dbellcreations@gmail.com']));
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_whatnot_loss_report_uses_estimates_without_requiring_payroll(): void
    {
        foreach ([['negative', -25], ['positive', 75], ['zero', 0]] as [$title,$net]) {
            Show::create(['title'=>$title,'show_date'=>'2026-10-14','status'=>'draft','is_operational'=>true,'whatnot_net'=>$net]);
        }
        Show::create(['title'=>'cancelled','show_date'=>'2026-10-14','status'=>'cancelled','is_operational'=>true,'whatnot_net'=>-80]);
        $rows=app(WhatnotLossReport::class)->rows(Carbon::parse('2026-10-14'),Carbon::parse('2026-10-14'));
        $this->assertSame(['negative'],$rows->pluck('title')->all());
        Livewire::test(WhatnotLosses::class)->assertSee('negative')->assertSee('25.00');
    }
    public function test_notification_drawer_opens_and_schedule_validation_is_enforced(): void
    {
        Livewire::test(NotificationSettings::class)->call('configure','daily_whatnot_losses')
            ->assertSet('configureEvent','daily_whatnot_losses')->assertSee('Summary schedule')
            ->set('lossSchedules.daily_whatnot_losses.time','25:99')->call('save')
            ->assertHasErrors(['lossSchedules.daily_whatnot_losses.time']);
        Livewire::withQueryParams(['configure'=>'weekly_whatnot_losses'])->test(NotificationSettings::class)
            ->assertSet('configureEvent','weekly_whatnot_losses')->assertSee('Summary schedule');
    }
    public function test_report_overview_renders_and_does_not_treat_missing_costs_as_final_profit(): void
    {
        Show::create(['title'=>'Incomplete','show_date'=>'2026-10-14','status'=>'draft','is_operational'=>true,'whatnot_net'=>100]);
        Livewire::test(ReportsOverview::class)->assertSee('Report Library')->assertSee('Profit After Paid Runs')->assertSee('Pending Financials');
    }
    public function test_empty_loss_summary_does_not_notify_anyone(): void
    {
        Notification::fake();
        $this->artisan('reports:whatnot-loss-summary',['--period'=>'day','--date'=>'2026-10-14'])->assertSuccessful();
        Notification::assertNothingSent();
    }
    public function test_scheduled_summary_respects_time_and_does_not_repeat(): void
    {
        Notification::fake();
        config(['app.display_timezone'=>'America/Chicago']);
        Show::create(['title'=>'Loss','show_date'=>'2026-10-14','status'=>'draft','is_operational'=>true,'whatnot_net'=>-20]);
        Setting::set('whatnot_loss_schedules',json_encode(['daily_whatnot_losses'=>['time'=>'18:00','day'=>5]]));
        $this->artisan('reports:whatnot-loss-summary',['--scheduled'=>true])->assertSuccessful();
        $this->artisan('reports:whatnot-loss-summary',['--scheduled'=>true])->assertSuccessful();
        Notification::assertCount(1);
    }
}
