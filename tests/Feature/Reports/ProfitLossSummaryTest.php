<?php

namespace Tests\Feature\Reports;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\ProfitLossSummaryNotification;
use App\Notifications\VortexAlert;
use App\Support\NotificationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Daily and weekly P&L summaries list every show; a completed losing show is flagged once. */
class ProfitLossSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Streamer $jordan;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 13:00:00');
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->jordan = Streamer::create(['name' => 'Jordan', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $title, string $date, float $net, float $pay): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id, 'title' => $title, 'show_date' => $date, 'gross_revenue' => $net + 50,
            'whatnot_net' => $net, 'tips' => 0, 'status' => 'reconciled', 'is_operational' => true, 'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($this->jordan->id, ['is_primary' => true]);
        Payout::create(['streamer_id' => $this->jordan->id, 'show_id' => $show->id, 'payout_type' => 'profit_share', 'calculated_payout' => $pay, 'gross_show_revenue' => $net + 50, 'status' => 'paid']);

        return $show;
    }

    public function test_the_events_can_be_set_up_in_notification_settings(): void
    {
        foreach (['daily_pl_summary', 'weekly_pl_summary', 'show_lost_money', 'missed_clock_out'] as $event) {
            $this->assertNotNull(NotificationCatalog::get($event), $event);
        }
    }

    public function test_the_daily_summary_lists_every_show_losing_first(): void
    {
        Notification::fake();
        $this->show('Winner Night', '2026-10-14', 900, 100);
        $this->show('Loser Night', '2026-10-14', 200, 300);
        $this->show('Last Week Show', '2026-10-07', 500, 50);

        $this->artisan('reports:pl-summary', ['period' => 'day'])->assertSuccessful();

        Notification::assertSentTo($this->owner, ProfitLossSummaryNotification::class, function (ProfitLossSummaryNotification $n) {
            $mail = implode("\n", $n->toMail($this->owner)->introLines);

            return $n->summary['totals']['shows'] === 2
                && $n->summary['totals']['losing'] === 1
                && strpos($mail, 'Loser Night') < strpos($mail, 'Winner Night')
                && str_contains($mail, 'Shows that lost money')
                && ! str_contains($mail, 'Last Week Show');
        });
    }

    public function test_the_weekly_summary_covers_last_week(): void
    {
        Notification::fake();
        $this->show('Last Week Show', '2026-10-07', 500, 50);
        $this->show('This Week Show', '2026-10-13', 500, 50);

        $this->artisan('reports:pl-summary', ['period' => 'week'])->assertSuccessful();

        Notification::assertSentTo($this->owner, ProfitLossSummaryNotification::class,
            fn ($n) => array_column($n->summary['shows'], 'title') === ['Last Week Show']);
    }

    public function test_a_show_that_lost_money_is_alerted_once_only_when_complete(): void
    {
        Notification::fake();
        $show = $this->show('Loser Night', '2026-10-14', 200, 300);

        // No COGS approved yet: the figure is not final, so no alert.
        $this->artisan('reports:losing-shows')->assertSuccessful();
        Notification::assertNothingSent();

        $item = \App\Models\InventoryItem::create(['name' => 'Box', 'is_active' => true]);
        $loc = \App\Models\InventoryLocation::create(['name' => 'Main']);
        $dr = \App\Models\DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $this->jordan->id, 'status' => 'approved']);
        \App\Models\DeductionRequestLine::create(['deduction_request_id' => $dr->id, 'inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity_suggested' => 1, 'quantity_approved' => 1, 'unit_cost_snapshot' => 10, 'raw_description' => 'x', 'line_total' => 10]);

        $this->artisan('reports:losing-shows')->assertSuccessful();
        $this->artisan('reports:losing-shows')->assertSuccessful();

        Notification::assertSentToTimes($this->owner, VortexAlert::class, 1);
    }
}
