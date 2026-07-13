<?php

namespace Tests\Feature\AI;

use App\AI\Tools\InventoryLookupTool;
use App\AI\Tools\ShowPnlTool;
use App\AI\Tools\StreamerBalanceTool;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
    }

    public function test_inventory_lookup_reports_stock_on_hand(): void
    {
        $product = Product::create(['name' => '2024 Prizm Basketball Hobby', 'sku' => 'PRZ-1', 'is_active' => true]);
        $loc1 = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $loc2 = InventoryLocation::create(['name' => 'Overflow', 'type' => 'other', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $product->id, 'inventory_location_id' => $loc1->id, 'quantity' => 7]);
        InventoryStock::create(['inventory_item_id' => $product->id, 'inventory_location_id' => $loc2->id, 'quantity' => 5]);

        $result = (new InventoryLookupTool())->run(['query' => 'Prizm']);

        $this->assertTrue($result->success);
        $this->assertSame(12.0, $result->data['matches'][0]['stock_on_hand']);
        $this->assertStringContainsString('12 on hand', $result->summary);
    }

    public function test_inventory_lookup_reports_no_match(): void
    {
        $result = (new InventoryLookupTool())->run(['query' => 'nonexistent widget']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No active product', $result->summary);
    }

    public function test_streamer_balance_by_name(): void
    {
        Streamer::create(['name' => 'Jordan Breaks', 'status' => 'active', 'total_earnings_due' => 300, 'total_earnings_paid' => 120]);

        $result = (new StreamerBalanceTool())->run(['name' => 'Jordan']);

        $this->assertTrue($result->success);
        $this->assertSame(180.0, $result->data['outstanding']);
        $this->assertStringContainsString('Jordan Breaks is owed $180.00', $result->summary);
    }

    public function test_streamer_balance_roster_total(): void
    {
        Streamer::create(['name' => 'A', 'status' => 'active', 'total_earnings_due' => 100, 'total_earnings_paid' => 0]);
        Streamer::create(['name' => 'B', 'status' => 'active', 'total_earnings_due' => 50, 'total_earnings_paid' => 50]);

        $result = (new StreamerBalanceTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame(100.0, $result->data['total_outstanding']);
    }

    public function test_show_pnl_for_most_recent_show(): void
    {
        Show::create(['title' => 'Old Show', 'show_date' => '2026-01-01', 'gross_revenue' => 100, 'whatnot_net' => 80, 'tips' => 0]);
        Show::create(['title' => 'Latest Rip', 'show_date' => '2026-07-01', 'gross_revenue' => 500, 'whatnot_net' => 420, 'tips' => 30]);

        $result = (new ShowPnlTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame('Latest Rip', $result->data['show']);
        $this->assertStringContainsString('Latest Rip', $result->summary);
    }

    public function test_show_pnl_by_title(): void
    {
        Show::create(['title' => 'Old Show', 'show_date' => '2026-01-01', 'gross_revenue' => 100, 'whatnot_net' => 80, 'tips' => 0]);
        Show::create(['title' => 'Latest Rip', 'show_date' => '2026-07-01', 'gross_revenue' => 500, 'whatnot_net' => 420, 'tips' => 30]);

        $result = (new ShowPnlTool())->run(['title' => 'Old']);

        $this->assertTrue($result->success);
        $this->assertSame('Old Show', $result->data['show']);
    }

    public function test_tools_require_admin_or_owner(): void
    {
        $owner = $this->owner();
        $stranger = User::factory()->create(['email' => 'nobody@example.com']);

        foreach ([new InventoryLookupTool(), new StreamerBalanceTool(), new ShowPnlTool()] as $tool) {
            $this->assertTrue($tool->authorize($owner), $tool->name() . ' should allow owner');
            $this->assertFalse($tool->authorize($stranger), $tool->name() . ' should deny non-admin');
            $this->assertFalse($tool->authorize(null), $tool->name() . ' should deny guest');
        }
    }
}
