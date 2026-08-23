<?php

namespace Tests\Feature\Console;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tells apart "Whatnot did not say" from "we did not read it".
 *
 * Those two produce the same empty tile on a show page and lead to completely
 * different work — one is a scraper fix, the other is nothing to fix at all —
 * and the only way to tell is whether the field is empty on every show or on
 * some of them.
 */
class WhatnotAnalyticsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    /** @param array<string, mixed> $attributes */
    private function importedShow(array $attributes = []): Show
    {
        return Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Show ' . uniqid(),
            'show_date'          => now()->subDays(2)->toDateString(),
            'import_source'      => 'auto_whatnot',
            'created_by'         => $this->owner->id,
        ], $attributes));
    }

    public function test_a_field_missing_on_every_show_is_blamed_on_the_scraper(): void
    {
        $this->importedShow(['show_duration' => null]);
        $this->importedShow(['show_duration' => null]);

        $this->artisan('whatnot:analytics-coverage')
            ->expectsOutputToContain('never arrives')
            ->assertSuccessful();
    }

    public function test_a_fully_populated_import_is_not_flagged_at_all(): void
    {
        // Every field set, which is what a healthy scrape looks like. Setting
        // only the one under test would leave the rest legitimately missing
        // and the report would flag those — correctly, but it would not be
        // testing what it claims to.
        $full = [
            'show_duration' => 154, 'start_time' => '19:00:00', 'end_time' => '21:34:00',
            'units_sold' => 98, 'gross_revenue' => 8801, 'whatnot_net' => 6496.34,
            'completed_earnings' => 120, 'avg_order_value' => 89.81, 'tips' => 12,
            'giveaway_spend' => 25.58, 'giveaways_count' => 59, 'buyers_count' => 29,
            'first_time_buyers' => 4, 'returning_buyers' => 25, 'shares_count' => 45,
            'max_concurrent_viewers' => 205, 'total_views' => 3931, 'avg_order_rating' => 4.85,
        ];

        $this->importedShow($full);
        $this->importedShow($full);

        $this->artisan('whatnot:analytics-coverage')
            ->doesntExpectOutputToContain('never arrives')
            ->doesntExpectOutputToContain('suspicious')
            ->assertSuccessful();
    }

    public function test_a_field_that_is_always_zero_where_zero_is_odd_is_called_suspicious(): void
    {
        // avg_order_rating being zero on every show is not a quiet month, it
        // is a figure being read wrongly — Whatnot leaves it empty until a
        // show is rated rather than reporting a rating of nothing.
        $this->importedShow(['avg_order_rating' => 0]);
        $this->importedShow(['avg_order_rating' => 0]);

        $this->artisan('whatnot:analytics-coverage')
            ->expectsOutputToContain('suspicious')
            ->assertSuccessful();
    }

    public function test_a_field_that_is_always_zero_where_zero_is_ordinary_is_left_alone(): void
    {
        // Plenty of shows genuinely give nothing away.
        $this->importedShow(['giveaways_count' => 0, 'avg_order_rating' => 4.8]);
        $this->importedShow(['giveaways_count' => 0, 'avg_order_rating' => 4.9]);

        $this->artisan('whatnot:analytics-coverage')
            ->expectsOutputToContain('plausible')
            ->assertSuccessful();
    }

    public function test_shows_added_by_hand_are_not_counted_against_the_scraper(): void
    {
        // A manual show has none of these fields and never will. Counting it
        // would report every field as missing on a system working perfectly.
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Added by hand',
            'show_date'          => now()->toDateString(),
            'created_by'         => $this->owner->id,
        ]);

        $this->artisan('whatnot:analytics-coverage')
            ->expectsOutputToContain('No Whatnot-imported shows')
            ->assertSuccessful();
    }

    public function test_shows_outside_the_window_are_not_counted(): void
    {
        $this->importedShow(['show_date' => now()->subDays(400)->toDateString(), 'show_duration' => 154]);

        $this->artisan('whatnot:analytics-coverage', ['--days' => 30])
            ->expectsOutputToContain('No Whatnot-imported shows')
            ->assertSuccessful();
    }
}
