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
}
