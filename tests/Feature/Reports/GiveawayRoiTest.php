<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\GiveawayRoiReport;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Services\GiveawayRoi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Giveaway cost per show (product at cost + Whatnot spend) against net and new buyers. */
class GiveawayRoiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'reporting']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
    }

    public function test_cost_adds_product_given_away_and_whatnot_spend(): void
    {
        $streamer = Streamer::create(['name' => 'Ana', 'status' => 'active']);
        $with = Show::create(['title' => 'Big giveaway night', 'show_date' => today()->subDays(2), 'status' => 'reconciled',
            'whatnot_net' => 900, 'gross_revenue' => 1000, 'buyers_count' => 40, 'first_time_buyers' => 10, 'giveaway_spend' => 20, 'giveaways_count' => 5]);
        $entry = StreamerLogEntry::create(['show_id' => $with->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        StreamerLogItem::create(['streamer_log_entry_id' => $entry->id, 'item_name' => 'Pack', 'quantity' => 4, 'unit_cost' => 5, 'disposition' => 'giveaway']);
        StreamerLogItem::create(['streamer_log_entry_id' => $entry->id, 'item_name' => 'Box', 'quantity' => 1, 'unit_cost' => 80, 'disposition' => 'sold']);
        Show::create(['title' => 'Quiet night', 'show_date' => today()->subDays(3), 'status' => 'reconciled', 'whatnot_net' => 300, 'first_time_buyers' => 2]);
        Show::create(['title' => 'Cancelled', 'show_date' => today()->subDays(3), 'status' => 'cancelled', 'whatnot_net' => 0]);

        $report = app(GiveawayRoi::class)->between(today()->subDays(29)->toDateString(), today()->toDateString());
        $rows = $report['shows']->keyBy('title');

        $this->assertCount(2, $rows);
        $this->assertSame(40.0, $rows['Big giveaway night']['cost']);           // 4 × $5 product + $20 Whatnot
        $this->assertSame(4.0, $rows['Big giveaway night']['cost_per_new_buyer']);
        $this->assertSame(22.5, $rows['Big giveaway night']['net_per_dollar']);
        $this->assertFalse($rows['Quiet night']['has_giveaways']);
        $this->assertSame(900.0, $report['with']['avg_net']);
        $this->assertSame(300.0, $report['without']['avg_net']);
        $this->assertSame(10, $report['totals']['new_buyers']);

        Livewire::test(GiveawayRoiReport::class)->assertOk()->assertSee('Big giveaway night')->assertSee('$40.00');
    }
}
