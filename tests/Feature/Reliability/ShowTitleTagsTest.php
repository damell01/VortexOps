<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Services\ShowFormatAnalytics;
use App\Support\ShowTitleTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTitleTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_titles_are_tagged_and_near_misses_are_not(): void
    {
        $this->assertSame(['giveaways', 'dollar_starts'], ShowTitleTags::for('🍀MASSIVE GIVVYS AND $1 STARTS W/NIKO🍀'));
        $this->assertSame(['giveaways', 'dollar_starts'], ShowTitleTags::for("🍀HUGE GIVY's AND \$1 STARTS W/NIKO🍀"));
        $this->assertSame(['giveaways', 'pop_up'], ShowTitleTags::for('FREE GIVEY! POP UP w/Butler_ Vortex'));
        $this->assertSame(['dollar_starts', 'free_packs'], ShowTitleTags::for('🎁FREE BOOSTER PACKS every 5 minutes /$1 starts W/Dennis_VortexCollects'));
        $this->assertSame([], ShowTitleTags::for('Trail Blazing Thursday w/ Connor'));
        $this->assertSame([], ShowTitleTags::for('Given time, he gives back'));
        $this->assertSame([], ShowTitleTags::for('$10 starts only'));
    }

    private function show(array $data): Show
    {
        return Show::withoutEvents(fn () => Show::create(array_merge(['show_date' => now()->subDays(3)->toDateString(), 'status' => 'reconciled'], $data)));
    }

    public function test_nightly_command_tags_untagged_and_outdated_shows_only(): void
    {
        $new = $this->show(['title' => 'MASSIVE GIVVYS']);
        $old = $this->show(['title' => '$1 STARTS']);
        $old->forceFill(['title_tags' => ['mystery'], 'title_tags_version' => 0])->saveQuietly();
        $current = $this->show(['title' => 'POP UP']);
        $current->forceFill(['title_tags' => ['hand_set'], 'title_tags_version' => ShowTitleTags::VERSION])->saveQuietly();

        $this->artisan('shows:tag-titles')->expectsOutput('Tagged 2 show(s).')->assertSuccessful();

        $this->assertSame(['giveaways'], $new->fresh()->title_tags);
        $this->assertSame(['dollar_starts'], $old->fresh()->title_tags);
        $this->assertSame(['hand_set'], $current->fresh()->title_tags);

        $this->artisan('shows:tag-titles --all')->assertSuccessful();
        $this->assertSame(['pop_up'], $current->fresh()->title_tags);
    }

    public function test_report_compares_earnings_per_hour_by_tag(): void
    {
        // Giveaways: $600 over 2 hours = $300/hr. Untagged: $200 over 2 hours = $100/hr.
        $this->show(['title' => 'GIVVYS night', 'gross_revenue' => 600, 'completed_earnings' => 480, 'show_duration' => 120]);
        $this->show(['title' => 'Plain show', 'gross_revenue' => 200, 'whatnot_net' => 150, 'show_duration' => 120]);
        $this->show(['title' => 'Cancelled GIVVYS', 'status' => 'cancelled', 'gross_revenue' => 9999, 'show_duration' => 60]);
        $this->artisan('shows:tag-titles');

        $result = app(ShowFormatAnalytics::class)->compareTitleTags(now()->subMonth());

        $this->assertSame(200.0, $result['overall']['gross_per_hour']);
        $this->assertSame(157.5, $result['overall']['net_per_hour']);
        $this->assertSame('giveaways', $result['rows'][0]['tag']);
        $this->assertSame(300.0, $result['rows'][0]['gross_per_hour']);
        $this->assertSame(240.0, $result['rows'][0]['net_per_hour']);
        $this->assertSame(50.0, $result['rows'][0]['gross_vs_overall_pct']);
        $this->assertSame('No tags in title', $result['rows'][1]['label']);
        $this->assertSame(-50.0, $result['rows'][1]['gross_vs_overall_pct']);
        $this->assertSame(0, $result['untagged']);
    }
}
