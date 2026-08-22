<?php

namespace Tests\Feature\Shows;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShowReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private Streamer $streamer;
    private InventoryLocation $location;
    private InventoryItem $item;
    private Show $show;
    private StreamerLogEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->creator = User::factory()->create();
        $this->streamer = Streamer::create(['name' => 'Workflow Streamer', 'status' => 'active']);
        $this->location = InventoryLocation::create([
            'name' => 'Workflow Streamer Inventory',
            'type' => 'streamer_inventory',
            'streamer_id' => $this->streamer->id,
            'status' => 'active',
        ]);
        $this->item = InventoryItem::create([
            'name' => 'Workflow Product',
            'average_cost' => 10,
            'is_active' => true,
            'is_container' => false,
        ]);
        InventoryStock::create([
            'inventory_item_id' => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity' => 20,
        ]);

        $this->show = Show::create([
            'title' => 'Workflow Test Show',
            'show_date' => today()->toDateString(),
            'status' => 'pending_review',
            'created_by' => $this->creator->id,
        ]);
        $this->show->streamers()->attach($this->streamer->id, ['is_primary' => true]);

        $this->entry = StreamerLogEntry::create([
            'show_id' => $this->show->id,
            'streamer_id' => $this->streamer->id,
            'status' => 'pending',
        ]);
    }

    private function addMatchedLine(int $quantity = 3, string $disposition = 'sold'): void
    {
        $this->entry->items()->create([
            'inventory_item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'quantity' => $quantity,
            'disposition' => $disposition,
            'unit_cost' => 10,
        ]);
    }

    public function test_exceptions_only_auto_approves_a_clean_report_and_posts_inventory(): void
    {
        Setting::set('show_inventory_posting_policy', 'clean_only');
        Setting::set('show_report_review_policy', 'exceptions_only');
        $this->addMatchedLine(3, 'giveaway');

        $problems = $this->entry->submitReport();
        $this->entry->refresh();

        $this->assertSame([], $problems);
        $this->assertSame('admin_approved', $this->entry->status);
        $this->assertSame('approved', $this->entry->approval_status);
        $this->assertNull($this->entry->reviewed_by);
        $this->assertEquals(17, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $this->item->id,
            'movement_type' => 'giveaway',
            'reference_type' => 'show',
            'reference_id' => $this->show->id,
        ]);
    }

    public function test_exceptions_only_keeps_unmatched_report_in_review(): void
    {
        Setting::set('show_inventory_posting_policy', 'clean_only');
        Setting::set('show_report_review_policy', 'exceptions_only');

        $this->entry->items()->create([
            'inventory_item_id' => null,
            'item_name' => 'Unknown Prize Pack',
            'quantity' => 1,
            'disposition' => 'giveaway',
        ]);

        $problems = $this->entry->submitReport();
        $this->entry->refresh();

        $this->assertNotEmpty($problems);
        $this->assertSame('streamer_reviewed', $this->entry->status);
        $this->assertSame('pending_approval', $this->entry->approval_status);
        $this->assertEquals(20, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertSame(0, InventoryMovement::where('reference_type', 'show')->where('reference_id', $this->show->id)->count());
    }

    public function test_on_approval_waits_then_posts_exactly_once(): void
    {
        Setting::set('show_inventory_posting_policy', 'on_approval');
        Setting::set('show_report_review_policy', 'required');
        $this->addMatchedLine(4, 'sold');

        $this->entry->submitReport();
        $this->assertEquals(20, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));

        $this->actingAs($this->creator);
        $firstProblems = $this->entry->fresh()->approveByAdmin();
        $this->assertSame([], $firstProblems);
        $this->assertEquals(16, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));

        // Re-running the posting method cannot deduct the same report line twice.
        $secondProblems = $this->entry->fresh()->postInventoryMovements();
        $this->assertSame([], $secondProblems);
        $this->assertEquals(16, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertSame(1, InventoryMovement::where('reference_type', 'show')->where('reference_id', $this->show->id)->count());
    }

    public function test_clean_report_can_auto_approve_on_approval_policy_and_post_during_auto_approval(): void
    {
        Setting::set('show_inventory_posting_policy', 'on_approval');
        Setting::set('show_report_review_policy', 'exceptions_only');
        $this->addMatchedLine(2, 'promo');

        $problems = $this->entry->submitReport();
        $this->entry->refresh();

        $this->assertSame([], $problems);
        $this->assertSame('admin_approved', $this->entry->status);
        $this->assertEquals(18, InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'promo',
            'reference_type' => 'show',
            'reference_id' => $this->show->id,
        ]);
    }
}
