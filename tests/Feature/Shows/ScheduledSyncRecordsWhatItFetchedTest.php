<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ten-minute job has to say what it fetched.
 *
 * whatnot:sync-show-index pulls analytics and shipments and wrote the timestamp
 * only into the raw_import_payload JSON, never onto the columns. Everything that
 * reads those columns therefore believed the work had never happened:
 * refresh-recent kept re-selecting shows whose figures were already in, and the
 * backfill reported 567 of 570 shows outstanding while their numbers sat in the
 * same row.
 *
 * The scraping itself needs a browser, so what is pinned here is the selection
 * and the bookkeeping either side of it.
 */
class ScheduledSyncRecordsWhatItFetchedTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = WhatnotChannel::create([
            'name'              => 'Vortex Breaks',
            'status'            => 'active',
            'include_in_import' => true,
        ]);
    }

    /**
     * RefreshDatabase has already run every migration by the time a test body
     * starts, so `migrate --path` finds it in the migrations table and does
     * nothing. Invoke the class directly against the rows this test just made.
     */
    private function runTheBackfill(): void
    {
        (require base_path('database/migrations/2026_08_26_170000_backfill_whatnot_sync_stamps.php'))->up();
    }

    private function show(array $attributes = []): Show
    {
        $show = Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'whatnot_show_id'    => (string) str()->uuid(),
            'title'              => 'Break',
            'show_date'          => now()->subDays(20)->toDateString(),
            'status'             => 'mapping',
        ], array_diff_key($attributes, array_flip(['last_analytics_synced_at', 'last_shipments_synced_at']))));

        $stamps = array_intersect_key($attributes, array_flip([
            'last_analytics_synced_at',
            'last_shipments_synced_at',
        ]));

        if ($stamps !== []) {
            $show->forceFill($stamps)->saveQuietly();
        }

        return $show->refresh();
    }

    public function test_the_stamps_are_backfilled_from_what_the_json_already_recorded(): void
    {
        $show = $this->show([
            'gross_revenue'      => 1200,
            'raw_import_payload' => [
                '_analytics_synced_at' => now()->subDays(3)->toIso8601String(),
                '_shipments_synced_at' => now()->subDays(3)->toIso8601String(),
            ],
        ]);

        $this->assertNull($show->last_analytics_synced_at);

        $this->runTheBackfill();

        $show->refresh();

        $this->assertNotNull($show->last_analytics_synced_at, 'a fetch the JSON recorded is still reported as never having happened');
        $this->assertNotNull($show->last_shipments_synced_at);
    }

    public function test_a_show_the_json_says_nothing_about_is_left_alone(): void
    {
        // Absence of a record is not evidence of a fetch.
        $show = $this->show(['raw_import_payload' => ['_show_index' => ['title' => 'Break']]]);

        $this->runTheBackfill();

        $this->assertNull($show->refresh()->last_analytics_synced_at);
    }

    public function test_a_show_with_analytics_but_no_shipments_is_still_selected(): void
    {
        // The query used to ask only about the analytics columns, so the moment
        // a show's figures arrived it stopped being visited — and its shipments
        // were never fetched by anything.
        $this->show([
            'gross_revenue'            => 1200,
            'completed_earnings'       => 900,
            'buyers_count'             => 40,
            'total_views'              => 900,
            'last_analytics_synced_at' => now()->subDay(),
        ]);

        $selected = Show::query()
            ->where('whatnot_channel_id', $this->channel->id)
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today())
            ->where(fn ($q) => $q
                ->whereNull('gross_revenue')
                ->orWhereNull('completed_earnings')
                ->orWhereNull('buyers_count')
                ->orWhereNull('total_views')
                ->orWhereNull('last_shipments_synced_at'))
            ->count();

        $this->assertSame(1, $selected);
    }

    public function test_a_fully_synced_show_is_not_selected_again(): void
    {
        $this->show([
            'gross_revenue'            => 1200,
            'completed_earnings'       => 900,
            'buyers_count'             => 40,
            'total_views'              => 900,
            'last_analytics_synced_at' => now()->subDay(),
            'last_shipments_synced_at' => now()->subDay(),
        ]);

        $selected = Show::query()
            ->where('whatnot_channel_id', $this->channel->id)
            ->where(fn ($q) => $q
                ->whereNull('gross_revenue')
                ->orWhereNull('completed_earnings')
                ->orWhereNull('buyers_count')
                ->orWhereNull('total_views')
                ->orWhereNull('last_shipments_synced_at'))
            ->count();

        $this->assertSame(0, $selected);
    }
}
