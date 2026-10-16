<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\WeeklyOwnerSummaryNotification;
use App\Services\WeeklyOwnerSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WeeklyOwnerSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 08:00:00'); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $date, array $data, array $streamers = []): Show
    {
        $show = Show::withoutEvents(fn () => Show::create(array_merge(['title' => 'Show', 'show_date' => $date, 'status' => 'reconciled'], $data)));
        $show->streamers()->attach(collect($streamers)->pluck('id')->all());

        return $show;
    }

    public function test_summary_compares_last_week_and_breaks_it_down(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        $niko = Streamer::create(['name' => 'Niko']);
        $dennis = Streamer::create(['name' => 'Dennis']);

        // Last week (Oct 5–11): $1,500 gross.
        $this->show('2026-10-06', ['title' => 'MASSIVE GIVVYS', 'whatnot_channel_id' => $breaks->id, 'gross_revenue' => 1000, 'completed_earnings' => 800, 'show_duration' => 120], [$niko]);
        $this->show('2026-10-08', ['title' => 'GIVVYS again', 'whatnot_channel_id' => $breaks->id, 'gross_revenue' => 300, 'whatnot_net' => 250, 'show_duration' => 60], [$niko]);
        $this->show('2026-10-10', ['title' => 'Plain', 'whatnot_channel_id' => $collects->id, 'gross_revenue' => 200, 'whatnot_net' => 150, 'show_duration' => 120], [$dennis]);
        $this->show('2026-10-09', ['title' => 'Cancelled', 'status' => 'cancelled', 'gross_revenue' => 5000]);
        // The week before: $1,000 gross.
        $this->show('2026-09-30', ['title' => 'Earlier', 'gross_revenue' => 1000]);
        $this->artisan('shows:tag-titles');

        $s = app(WeeklyOwnerSummary::class)->build(now()->subWeek());

        $this->assertSame('Oct 5 – Oct 11, 2026', $s['week_label']);
        $this->assertSame(['shows' => 3, 'gross' => 1500.0, 'net' => 1200.0, 'hours' => 5.0, 'units' => 0], $s['totals']);
        $this->assertSame(50.0, $s['change']['gross_pct']);
        $this->assertSame(['Vortex Breaks', 'Vortex Collects'], array_column($s['channels'], 'name'));
        $this->assertSame(['Niko', 'Dennis'], array_column($s['streamers'], 'name'));
        $this->assertSame(1300.0, $s['streamers'][0]['gross']);
        $this->assertSame('Giveaways', $s['tags'][0]['label']);

        $html = (string) (new WeeklyOwnerSummaryNotification($s))->toMail(new User)->render();
        foreach (['$1,500.00 gross', '+50.0% vs the week before', '>Vortex Breaks</td>', '>$1,300.00</td>', '>Niko</td>', '>Giveaways</td>'] as $text) {
            $this->assertStringContainsString($text, html_entity_decode($html));
        }
    }

    public function test_command_sends_to_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        User::factory()->create();

        $this->artisan('reports:weekly-owner-summary')->assertSuccessful();

        Notification::assertSentTo($owner, WeeklyOwnerSummaryNotification::class);
        Notification::assertCount(1);
    }
}
