<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\Streamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The dashboard's "trending down" list now comes from one query, with the same numbers. */
class StreamerTrendBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_batched_trend_matches_the_per_streamer_one_in_one_query(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $make = function (string $name, array $weekly) {
            $streamer = Streamer::create(['name' => $name, 'status' => 'active']);
            foreach ($weekly as $weeksAgo => $gross) {
                $show = Show::withoutEvents(fn () => Show::create([
                    'title' => "{$name} {$weeksAgo}", 'status' => 'draft', 'gross_revenue' => $gross,
                    'show_date' => now()->subWeeks($weeksAgo)->startOfWeek()->addDays(2)->toDateString(),
                ]));
                $streamer->shows()->attach($show->id);
            }
            return $streamer;
        };
        $down = $make('Down', [1 => 100, 2 => 400, 3 => 500, 4 => 300]);
        $steady = $make('Steady', [1 => 300, 2 => 300, 3 => 300, 4 => 300]);
        $quiet = $make('Quiet', [2 => 300]);

        DB::enableQueryLog();
        $batched = Streamer::performanceTrendsFor([$down, $steady, $quiet]);
        $this->assertCount(1, DB::getQueryLog());

        foreach ([$down, $steady, $quiet] as $s) {
            $one = $s->performanceTrend();
            $this->assertSame($one === null, $batched[$s->id] === null, $s->name);
            if ($one) $this->assertEqualsWithDelta($one['change_pct'], $batched[$s->id]['change_pct'], 0.001, $s->name);
        }
        $this->assertLessThan(-30, $batched[$down->id]['change_pct']);
    }
}
