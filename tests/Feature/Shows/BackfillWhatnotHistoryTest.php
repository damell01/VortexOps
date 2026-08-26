<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Counting the back catalogue that is still missing its Whatnot numbers.
 *
 * The scraping itself needs a browser and a live session, so what is worth
 * pinning down here is the arithmetic around it: which shows count as
 * outstanding, and the fact that a refresh run only ever visits one channel —
 * a backlog on any other one would otherwise sit there for ever with nothing
 * saying why.
 */
class BackfillWhatnotHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function channel(string $name, array $attributes = []): WhatnotChannel
    {
        return WhatnotChannel::create(array_merge([
            'name'   => $name,
            'status' => 'active',
        ], $attributes));
    }

    private function show(WhatnotChannel $channel, array $attributes = []): Show
    {
        $show = Show::create(array_merge([
            'whatnot_channel_id' => $channel->id,
            'whatnot_show_id'    => (string) str()->uuid(),
            'title'              => 'Break',
            'show_date'          => now()->subDays(10)->toDateString(),
            'status'             => 'mapping',
        ], $attributes));

        // The sync stamps are written by the scraper, not mass-assigned.
        $stamps = array_intersect_key($attributes, array_flip([
            'last_analytics_synced_at',
            'last_shipments_synced_at',
        ]));

        if ($stamps !== []) {
            $show->forceFill($stamps)->save();
        }

        return $show;
    }

    /** Everything a completed show should have: the figures and a shipment sync. */
    private function fullySynced(): array
    {
        return [
            'gross_revenue'            => 1200,
            'completed_earnings'       => 900,
            'buyers_count'             => 40,
            'total_views'              => 900,
            'last_analytics_synced_at' => now()->subDay(),
            'last_shipments_synced_at' => now()->subDay(),
        ];
    }

    public function test_a_show_with_its_figures_and_shipments_is_not_outstanding(): void
    {
        $channel = $this->channel('Vortex Cards', ['include_in_import' => true]);

        $this->show($channel, $this->fullySynced());

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('Nothing outstanding')
            ->assertSuccessful();
    }

    public function test_a_stamp_without_the_figures_is_still_outstanding(): void
    {
        // The stamp is only written by the two commands driving
        // whatnot-production-sync; the figures also arrive with the show import.
        // Counting the stamp reported 567 of 570 shows outstanding on a channel
        // where a third already had every number.
        $channel = $this->channel('Vortex Cards', ['include_in_import' => true]);

        $this->show($channel, [
            'last_analytics_synced_at' => now()->subDay(),
            'last_shipments_synced_at' => now()->subDay(),
        ]);

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('1 still missing analytics or shipments')
            ->assertSuccessful();
    }

    public function test_figures_without_a_stamp_are_not_counted_as_missing(): void
    {
        // The other half: hundreds of shows had their numbers and no stamp, and
        // no amount of scraping would ever have moved the count.
        $channel = $this->channel('Vortex Cards', ['include_in_import' => true]);

        $show = $this->show($channel, [
            'gross_revenue'      => 1200,
            'completed_earnings' => 900,
            'buyers_count'       => 40,
            'total_views'        => 900,
        ]);

        \App\Models\Shipment::create([
            'show_id'  => $show->id,
            'tracking' => 'TRACK-1',
            'status'   => 'delivered',
        ]);

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('Nothing outstanding')
            ->assertSuccessful();
    }

    public function test_a_show_missing_either_stamp_is_outstanding(): void
    {
        $channel = $this->channel('Vortex Cards', ['include_in_import' => true]);

        // Shipments arrived, analytics never did — still a hole, and the
        // mirror image of it.
        $this->show($channel, ['last_shipments_synced_at' => now()->subDay()]);
        $this->show($channel, array_merge($this->fullySynced(), ['last_shipments_synced_at' => null]));

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('2 still missing analytics or shipments')
            ->expectsOutputToContain('1 pass of up to 20')
            ->assertSuccessful();
    }

    public function test_a_show_still_to_come_is_not_counted(): void
    {
        // Tomorrow's show has no numbers because it has not happened.
        $channel = $this->channel('Vortex Cards', ['include_in_import' => true]);

        $this->show($channel, ['show_date' => now()->addDay()->toDateString()]);
        $this->show($channel, $this->fullySynced());

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('Nothing outstanding')
            ->assertSuccessful();
    }

    public function test_a_backlog_on_another_channel_is_called_out(): void
    {
        // A refresh run visits one channel. Without this the second channel's
        // shows never fill and the command looks broken rather than scoped.
        $primary = $this->channel('Vortex Cards', ['include_in_import' => true]);
        $other   = $this->channel('Vortex Vintage', ['include_in_import' => false]);

        $this->show($primary, [
            'last_analytics_synced_at' => now()->subDay(),
            'last_shipments_synced_at' => now()->subDay(),
        ]);
        $this->show($other);

        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('Vortex Vintage')
            ->assertSuccessful();
    }

    public function test_it_stops_cleanly_when_there_is_no_channel_at_all(): void
    {
        $this->artisan('whatnot:backfill-history --dry-run')
            ->expectsOutputToContain('No Whatnot channel')
            ->assertFailed();
    }
}
