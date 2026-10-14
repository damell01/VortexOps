<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\Handbook;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\InventoryManual;
use App\Support\ReportsManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The Reports & Analytics handbook holds to the same standard as the inventory one. */
class ReportsManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_step_has_its_screenshots_on_disk_and_a_field_table(): void
    {
        foreach (ReportsManual::sections() as $section) {
            foreach ($section['steps'] as $step) {
                $this->assertNotEmpty($step['body'], "\"{$step['title']}\" has no text");
                foreach (array_filter(array_merge([$step['shot'] ?? null], $step['more'] ?? [])) as $shot) {
                    $this->assertFileExists(public_path(InventoryManual::IMAGE_DIR.'/'.$shot), "{$shot} ({$step['title']})");
                }
                $this->assertNotEmpty($step['fields'], "\"{$step['title']}\" has no field table");
                foreach ($step['fields'] as $field) {
                    $this->assertCount(2, $field);
                    $this->assertNotEmpty(trim($field[1]), "field \"{$field[0]}\" in \"{$step['title']}\" is not explained");
                }
            }
        }
    }

    public function test_it_covers_every_report_in_the_menu(): void
    {
        $text = json_encode(ReportsManual::sections());

        foreach (['Reports Overview', 'Whatnot Performance', 'Whatnot Losses', 'Business Profitability', 'Ledger', 'Streamer Analytics', 'Inventory Reports', 'Notifications'] as $report) {
            $this->assertStringContainsString($report, $text, "the handbook does not cover {$report}");
        }
    }

    public function test_the_handbook_page_opens_it(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);

        // The test database's saved module list predates Reports; switch it on.
        Setting::set('enabled_admin_modules', json_encode(AdminModules::defaultEnabledSlugs()));
        AdminModules::flushMemo();

        Livewire::actingAs($owner)->test(Handbook::class)
            ->call('selectModule', 'reports')
            ->assertSet('module', 'reports')
            ->assertSee('Start here')
            ->assertSee('Alerts and summaries')
            ->call('openSection', 1)
            ->assertSee('Find shows that lost money on Whatnot')
            ->assertSee('Send Summary');
    }
}
