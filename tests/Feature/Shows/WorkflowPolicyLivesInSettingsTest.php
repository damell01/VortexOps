<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\DashboardImproved;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The post-show policies are settings, and they live in Settings.
 *
 * They were a panel of radio buttons at the top of the dashboard: chosen once
 * and then left alone for months, above the numbers that are the reason people
 * open that screen many times a day — and mostly in front of people without
 * the permission to change them.
 *
 * What they control has not moved, so these check that the same two keys are
 * still what gets written, and that the dashboard no longer offers them.
 */
class WorkflowPolicyLivesInSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();
        $this->actingAs($this->owner()->fresh());
    }

    public function test_the_settings_page_writes_the_posting_policy(): void
    {
        Livewire::test(AppSettings::class)
            ->set('show_inventory_posting_policy', 'on_approval')
            ->call('saveSettings');

        $this->assertSame('on_approval', Setting::get('show_inventory_posting_policy'));
    }

    public function test_the_settings_page_writes_the_review_policy(): void
    {
        Livewire::test(AppSettings::class)
            ->set('show_report_review_policy', 'exceptions_only')
            ->call('saveSettings');

        $this->assertSame('exceptions_only', Setting::get('show_report_review_policy'));
    }

    public function test_the_stored_policies_are_what_the_page_shows(): void
    {
        Setting::set('show_inventory_posting_policy', 'clean_only');
        Setting::set('show_report_review_policy', 'auto');

        Livewire::test(AppSettings::class)
            ->assertSet('show_inventory_posting_policy', 'clean_only')
            ->assertSet('show_report_review_policy', 'auto');
    }

    public function test_a_policy_value_that_is_not_offered_is_refused(): void
    {
        Setting::set('show_inventory_posting_policy', 'on_submit');

        Livewire::test(AppSettings::class)
            ->set('show_inventory_posting_policy', 'whenever_it_feels_like_it')
            ->call('saveSettings');

        $this->assertSame('on_submit', Setting::get('show_inventory_posting_policy'));
    }

    public function test_the_dashboard_no_longer_carries_the_controls(): void
    {
        $widgets = Livewire::test(DashboardImproved::class)
            ->instance()
            ->getWidgets();

        $this->assertNotContains(
            'App\\Filament\\Widgets\\ShowWorkflowControlWidget',
            array_map('strval', $widgets),
        );
    }

    public function test_the_dashboard_still_reports_what_is_waiting(): void
    {
        // Only the radio buttons moved. The counts beside them were the one
        // place these queues are reported, and losing them to a tidy-up would
        // be a worse outcome than the panel being in the wrong place.
        $widgets = array_map('strval', Livewire::test(DashboardImproved::class)->instance()->getWidgets());

        $this->assertContains('App\\Filament\\Widgets\\ShowQueueCountsWidget', $widgets);
    }
}
