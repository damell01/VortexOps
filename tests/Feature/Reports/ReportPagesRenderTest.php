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
            ->assertOk()->assertSee('Shows')->assertSee('Margin')
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

}
