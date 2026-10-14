<?php
namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_report_renders_kpis_and_custom_period(): void
    {
        $this->enableAdminModules();
        $this->actingAs((User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh());
        Livewire::test(\App\Filament\Pages\Reports::class)
            ->assertOk()->assertSee('Shows')->assertSee('Gross minus recorded COGS')
            ->call('setPeriod', '7')->assertOk()
            ->set('dateFrom', now()->subDays(14)->toDateString())
            ->set('dateTo', now()->toDateString())
            ->call('applyCustomRange')->assertOk();
    }
    public function test_invalid_custom_range_has_validation_errors(): void
    {
        $this->enableAdminModules();
        $this->actingAs((User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh());
        Livewire::test(\App\Filament\Pages\Reports::class)
            ->set('dateFrom', '2026-10-08')->set('dateTo', '2026-10-01')
            ->call('applyCustomRange')->assertHasErrors(['dateTo'])->assertOk();
    }

    public function test_authenticated_pages_render_the_navigation_shell(): void
    {
        $this->withoutVite();
        $this->enableAdminModules();
        $this->actingAs((User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh());
        $this->get('/admin/inventory-items')->assertOk()->assertSee('Main navigation');
        $this->get('/admin/reports')->assertOk()->assertSee('Main navigation');
    }

    public function test_whatnot_performance_page_renders_its_sections_from_real_figures(): void
    {
        $this->withoutVite();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $breaks = \App\Models\WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $cards = \App\Models\WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $streamer = \App\Models\Streamer::create(['name' => 'Caydenvcx', 'status' => 'active', 'payout_type' => 'flat_rate']);
        foreach ([[$breaks, 1200, 1000, 3], [$cards, 400, 350, 9]] as [$channel, $gross, $net, $daysAgo]) {
            $show = \App\Models\Show::withoutEvents(fn () => \App\Models\Show::create([
                'title' => 'Show', 'show_date' => now()->subDays($daysAgo)->toDateString(), 'status' => 'reconciled',
                'whatnot_channel_id' => $channel->id, 'gross_revenue' => $gross, 'whatnot_net' => $net, 'tips' => 25, 'units_sold' => 12,
            ]));
            $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        }

        $this->get(\App\Filament\Pages\Reports::getUrl())
            ->assertOk()
            ->assertSee('Whatnot Performance')
            ->assertSee('Show revenue, channel performance and streamer activity.')
            ->assertSee('Last 30 days')->assertSee('This year')->assertSee('Export CSV')
            ->assertSee('Units Sold')->assertSee('24')
            ->assertSee('Gross Revenue')->assertSee('$1,600.00')
            ->assertSee('Whatnot Est. Earnings')->assertSee('$1,350.00')
            ->assertSee('Recorded COGS')->assertSee('Not recorded')
            ->assertSee('Provisional')
            ->assertSee('Tips')->assertSee('$50.00')
            ->assertSee('Missing costs are not treated as verified zero costs.')
            ->assertSee('Revenue by Week')
            ->assertSee('Top Streamers')->assertSee('Caydenvcx')
            ->assertSee('Channel Performance')->assertSee('Vortex Breaks')->assertSee('Vortex Cards')
            ->assertSee('Compare two periods')->assertSee('Channel profit')->assertSee('Payout Pipeline')
            ->assertSee('Jump to Report')->assertSee('Whatnot Ledger')->assertSee('Profit &amp; Loss', false)
            ->assertSee(\App\Filament\Resources\WhatnotLedgerResource::getUrl('index'), false);

        $this->assertSame('Whatnot Performance', \App\Filament\Pages\Reports::getNavigationLabel());
        $this->assertStringEndsWith('/admin/reports', \App\Filament\Pages\Reports::getUrl());

        // The page's own channel picker narrows every figure.
        $page = Livewire::test(\App\Filament\Pages\Reports::class)
            ->set('channel', (string) $cards->id)
            ->assertSee('$400.00')
            ->set('weekMetric', 'units')->assertOk()
            ->set('streamerSort', 'shows')->assertOk()
            ->set('weekMetric', 'bogus')->assertSet('weekMetric', 'gross');
        $this->assertSame(400.0, $page->instance()->revenueSummary['gross']);
        $this->assertSame(1, $page->instance()->revenueSummary['shows']);
    }

    public function test_this_year_period_starts_on_january_first(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        Livewire::test(\App\Filament\Pages\Reports::class)
            ->call('setPeriod', '365')
            ->assertSet('dateFrom', now()->startOfYear()->toDateString())
            ->assertOk();
    }
}
