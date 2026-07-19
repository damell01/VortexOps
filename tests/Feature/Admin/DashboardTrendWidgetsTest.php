<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\OperationsOverviewWidget;
use App\Filament\Widgets\ShowsKpiWidget;
use App\Models\Show;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard KPI widgets gained the same period-over-period trend convention
 * already used on Streamer Analytics: a ↑/↓ + percent suffix and matching
 * color, plus a trailing sparkline via Stat::chart(). No prior-period
 * baseline (zero) means the trend suffix is omitted rather than showing a
 * misleading ±100%.
 */
class DashboardTrendWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    private function makeShow(string $date, array $attrs = []): Show
    {
        $creator = User::factory()->create();

        return Show::create(array_merge([
            'title'      => 'Show ' . $date,
            'show_date'  => $date,
            'status'     => 'reconciled',
            'created_by' => $creator->id,
            'whatnot_net'=> 100.0,
            'gross_revenue' => 120.0,
        ], $attrs));
    }

    public function test_shows_kpi_widget_shows_upward_trend_when_this_week_beats_last_week(): void
    {
        $this->actingAs($this->admin());

        // Last week: one show. This week: two shows — a clear improvement.
        $this->makeShow(now()->subWeek()->startOfWeek()->toDateString());
        $this->makeShow(now()->startOfWeek()->toDateString());
        $this->makeShow(now()->startOfWeek()->addDay()->toDateString());

        Livewire::test(ShowsKpiWidget::class)
            ->assertSee('vs last week')
            ->assertSee('↑');
    }

    public function test_shows_kpi_widget_omits_trend_when_no_prior_week_baseline(): void
    {
        $this->actingAs($this->admin());

        $this->makeShow(now()->startOfWeek()->toDateString());

        Livewire::test(ShowsKpiWidget::class)
            ->assertDontSee('vs last week');
    }

    public function test_operations_overview_widget_shows_month_over_month_trend(): void
    {
        $this->actingAs($this->admin());

        $this->makeShow(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), ['gross_revenue' => 50.0]);
        $this->makeShow(now()->startOfMonth()->toDateString(), ['gross_revenue' => 200.0]);

        Livewire::test(OperationsOverviewWidget::class)
            ->assertSee('vs last month')
            ->assertSee('↑');
    }
}
