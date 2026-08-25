<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\ShowFormatComparison;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\ShowFormatAnalytics;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Marking a show after the fact, and comparing the kinds against each other.
 */
class ShowFormatComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $this->channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);
    }

    private function show(array $attributes = []): Show
    {
        return Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Show ' . uniqid(),
            'show_date'          => today()->toDateString(),
            'status'             => 'reconciled',
            'created_by'         => $this->admin->id,
        ], $attributes));
    }

    private function rows(): array
    {
        return collect(app(ShowFormatAnalytics::class)->compare())->keyBy('label')->all();
    }

    public function test_formats_are_averaged_separately(): void
    {
        // The whole point: one mean across formats that behave nothing alike
        // describes none of them.
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 1000]);
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 1200]);
        $this->show(['show_format' => 'low_giveaway', 'whatnot_net' => 400]);

        $rows = $this->rows();

        $this->assertSame(1100.0, $rows['Sudden death']['avg_net']);
        $this->assertSame(2, $rows['Sudden death']['shows']);
        $this->assertSame(400.0, $rows['Low giveaway']['avg_net']);
    }

    public function test_each_format_is_measured_against_the_average_of_everything(): void
    {
        // Overall average across the three is 866.67.
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 1000]);
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 1200]);
        $this->show(['show_format' => 'low_giveaway', 'whatnot_net' => 400]);

        $rows = $this->rows();

        $this->assertGreaterThan(0, $rows['Sudden death']['net_vs_overall_pct']);
        $this->assertLessThan(0, $rows['Low giveaway']['net_vs_overall_pct']);
    }

    public function test_the_best_format_is_listed_first(): void
    {
        $this->show(['show_format' => 'low_giveaway', 'whatnot_net' => 100]);
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 900]);

        $this->assertSame('Sudden death', app(ShowFormatAnalytics::class)->compare()[0]['label']);
    }

    public function test_unclassified_shows_are_their_own_group_not_a_default(): void
    {
        // An unclassified show is not a standard one, and folding it into a
        // default would move a real format's average with shows nobody has
        // even looked at.
        $this->show(['show_format' => 'standard', 'whatnot_net' => 500]);
        $this->show(['whatnot_net' => 900]);

        $rows = $this->rows();

        $this->assertSame(500.0, $rows['Standard break']['avg_net']);
        $this->assertSame(900.0, $rows['Unclassified']['avg_net']);
        $this->assertNull($rows['Unclassified']['format']);
    }

    public function test_gross_stands_in_where_the_net_has_not_synced(): void
    {
        // Same rule the show page uses. A show reporting zero for its first
        // hours would otherwise drag its whole format down.
        $this->show(['show_format' => 'standard', 'gross_revenue' => 800, 'whatnot_net' => 0]);

        $this->assertSame(800.0, $this->rows()['Standard break']['avg_net']);
    }

    public function test_draft_and_cancelled_shows_are_left_out(): void
    {
        // They have no performance to average, and including them drags every
        // format toward zero by however many were abandoned.
        $this->show(['show_format' => 'standard', 'whatnot_net' => 600]);
        $this->show(['show_format' => 'standard', 'whatnot_net' => 0, 'status' => 'draft']);
        $this->show(['show_format' => 'standard', 'whatnot_net' => 0, 'status' => 'cancelled']);

        $rows = $this->rows();

        $this->assertSame(1, $rows['Standard break']['shows']);
        $this->assertSame(600.0, $rows['Standard break']['avg_net']);
    }

    public function test_a_period_with_no_shows_reports_nothing_rather_than_zeroes(): void
    {
        $this->assertSame([], app(ShowFormatAnalytics::class)->compare());
        $this->assertSame(0, app(ShowFormatAnalytics::class)->overall()['shows']);
    }

    public function test_the_range_filter_reaches_the_numbers(): void
    {
        $this->show(['show_format' => 'standard', 'whatnot_net' => 500, 'show_date' => today()->toDateString()]);
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 900, 'show_date' => today()->subDays(200)->toDateString()]);

        $component = Livewire::test(ShowFormatComparison::class)->set('range', '30');
        $labels = array_column($component->instance()->rows, 'label');

        $this->assertContains('Standard break', $labels);
        $this->assertNotContains('Sudden death', $labels);

        $component->set('range', '365');
        $this->assertContains('Sudden death', array_column($component->instance()->rows, 'label'));
    }

    public function test_the_page_renders_the_comparison(): void
    {
        $this->show(['show_format' => 'sudden_death', 'whatnot_net' => 1000]);

        Livewire::test(ShowFormatComparison::class)
            ->assertSuccessful()
            ->assertSee('Sudden death')
            ->assertSee('By format');
    }

    public function test_the_page_says_how_many_shows_still_need_classifying(): void
    {
        $this->show(['whatnot_net' => 100]);
        $this->show(['whatnot_net' => 200]);

        $this->assertSame(2, Livewire::test(ShowFormatComparison::class)->instance()->unclassifiedCount);
    }
}
