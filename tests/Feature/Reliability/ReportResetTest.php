<?php

namespace Tests\Feature\Reliability;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Resetting a report: the show goes back to having none, and nothing else is lost. */
class ReportResetTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private Streamer $streamer;

    private StreamerLogEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));

        $this->streamer = Streamer::create(['name' => 'Tester', 'status' => 'active', 'payout_type' => 'profit_share', 'payout_percentage' => 10]);
        $this->show = Show::create(['title' => 'Test Night', 'show_date' => now()->subDay()->toDateString(), 'gross_revenue' => 500]);
        $this->show->streamers()->attach($this->streamer->id, ['is_primary' => true]);
        $this->entry = StreamerLogEntry::create(['show_id' => $this->show->id, 'streamer_id' => $this->streamer->id, 'status' => 'streamer_reviewed', 'submitted_at' => now()]);
    }

    public function test_the_report_and_its_lines_are_gone_and_the_show_has_no_report(): void
    {
        StreamerLogItem::create(['streamer_log_entry_id' => $this->entry->id, 'item_name' => 'Test pack', 'quantity' => 2, 'disposition' => 'sold']);

        $this->entry->resetToNotStarted();

        $this->assertNull(StreamerLogEntry::find($this->entry->id));
        $this->assertSame(0, StreamerLogItem::count());
        $this->assertNull($this->show->fresh()->streamerLogEntry);
    }

    public function test_stock_the_report_took_is_put_back(): void
    {
        $location = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'RESET-1', 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 7]);
        StreamerLogItem::create(['streamer_log_entry_id' => $this->entry->id, 'inventory_item_id' => $item->id, 'item_name' => 'Hobby Box', 'quantity' => 3, 'deducted_quantity' => 3, 'disposition' => 'sold', 'unit_cost' => 10]);

        $this->entry->resetToNotStarted();

        $this->assertEqualsWithDelta(10.0, (float) InventoryStock::where('inventory_item_id', $item->id)->value('quantity'), 0.001);
    }

    public function test_draft_pay_from_the_report_goes_with_it(): void
    {
        Payout::create(['show_id' => $this->show->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 50, 'status' => 'draft']);

        $this->entry->resetToNotStarted();

        $this->assertSame(0, Payout::count());
    }

    public function test_pay_already_on_a_run_blocks_the_reset(): void
    {
        Payout::create(['show_id' => $this->show->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 50, 'status' => 'paid']);

        try {
            $this->entry->resetToNotStarted();
            $this->fail('a report with paid pay was reset');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pay run', $e->getMessage());
        }

        $this->assertNotNull(StreamerLogEntry::find($this->entry->id));
        $this->assertSame(1, Payout::count());
    }
}
