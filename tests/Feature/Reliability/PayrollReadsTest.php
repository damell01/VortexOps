<?php
namespace Tests\Feature\Reliability;

use App\Filament\Pages\PayrollOverview;
use App\Models\{DeductionRequest, DeductionRequestLine, InventoryItem, InventoryLocation, Show, Streamer, User, WhatnotChannel};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollReadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_overview_keeps_cogs_and_reuses_loaded_shows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $channel = WhatnotChannel::create(['name' => 'Payroll Reads', 'status' => 'active']);
        $streamer = Streamer::create(['name' => 'Reader', 'status' => 'active', 'payout_type' => 'flat_rate', 'flat_rate' => 100]);
        $show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Cost check', 'show_date' => now()->toDateString(), 'is_operational' => true, 'gross_revenue' => 500, 'whatnot_net' => 450, 'status' => 'mapping', 'created_by' => $user->id]);
        $show->streamers()->attach($streamer->id);
        $item = InventoryItem::create(['name' => 'Cards', 'unit_cost' => 5, 'is_active' => true]);
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $request = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'approved']);
        DeductionRequestLine::create(['deduction_request_id' => $request->id, 'inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity_suggested' => 5, 'quantity_approved' => 5, 'unit_cost_snapshot' => 5, 'line_total' => 25]);
        $page = new PayrollOverview;
        $method = new \ReflectionMethod($page, 'allCurrentWeekShows');
        $shows = $method->invoke($page);
        $row = $shows->firstWhere('id', $show->id);
        $this->assertNotNull($row);
        $this->assertSame(25.0, $row->getAttribute('pnl_summary')['cogs']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($shows, $method->invoke($page));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }
}
