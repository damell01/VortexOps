<?php

namespace Tests\Feature\AI;

use App\AI\Tools\PayoutBatchStatusTool;
use App\AI\Tools\PendingDeductionsTool;
use App\AI\Tools\ReorderListTool;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoreToolsTest extends TestCase
{
    use RefreshDatabase;

    private function stock(Product $p, float $qty): void
    {
        $loc = InventoryLocation::create(['name' => 'L' . uniqid(), 'type' => 'main_storage', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $p->id, 'inventory_location_id' => $loc->id, 'quantity' => $qty]);
    }

    public function test_reorder_list_flags_only_low_stock_items(): void
    {
        $low  = Product::create(['name' => 'Low Box', 'sku' => 'LOW-1', 'is_active' => true, 'reorder_level' => 10]);
        $ok   = Product::create(['name' => 'Fine Box', 'sku' => 'OK-1', 'is_active' => true, 'reorder_level' => 5]);
        $this->stock($low, 3);   // 3 <= 10 -> flagged
        $this->stock($ok, 50);   // 50 > 5  -> not flagged

        $result = (new ReorderListTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['items']);
        $this->assertSame('Low Box', $result->data['items'][0]['name']);
    }

    public function test_reorder_list_healthy_when_nothing_low(): void
    {
        $p = Product::create(['name' => 'Stocked', 'sku' => 'S-1', 'is_active' => true, 'reorder_level' => 5]);
        $this->stock($p, 100);

        $result = (new ReorderListTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->data['items']);
        $this->assertStringContainsString('healthy', $result->summary);
    }

    public function test_payout_batch_status_reports_latest_batch(): void
    {
        WeeklyPayoutBatch::create(['week_start' => '2026-06-01', 'week_end' => '2026-06-07', 'status' => 'paid', 'total_payout' => 1000]);
        WeeklyPayoutBatch::create(['week_start' => '2026-07-01', 'week_end' => '2026-07-07', 'status' => 'draft', 'total_payout' => 250]);

        $result = (new PayoutBatchStatusTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame('draft', $result->data['status']);
        $this->assertSame(250.0, $result->data['total_payout']);
        $this->assertStringContainsString('Draft', $result->summary);
    }

    public function test_payout_batch_status_handles_no_batches(): void
    {
        $result = (new PayoutBatchStatusTool())->run([]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('no payout batches', $result->summary);
    }

    public function test_pending_deductions_totals_cogs(): void
    {
        $show = \App\Models\Show::create(['title' => 'Rip Night', 'show_date' => '2026-07-01']);
        $streamer = \App\Models\Streamer::create(['name' => 'Deducted', 'status' => 'active']);
        $item = Product::create(['name' => 'Deduct Item', 'sku' => 'DI-1', 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'DL', 'type' => 'main_storage', 'status' => 'active']);
        $dr = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        $line = fn (string $desc, float $total) => DeductionRequestLine::create([
            'deduction_request_id' => $dr->id,
            'inventory_item_id'    => $item->id,
            'inventory_location_id' => $loc->id,
            'raw_description'      => $desc,
            'quantity_suggested'  => 1,
            'line_total'          => $total,
        ]);
        $line('Box A', 40);
        $line('Box B', 60);
        // An approved one should be excluded.
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'approved']);

        $result = (new PendingDeductionsTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['count']);
        $this->assertSame(100.0, $result->data['total_cogs']);
    }

    public function test_pending_deductions_none(): void
    {
        $result = (new PendingDeductionsTool())->run([]);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->data['count']);
    }
}
