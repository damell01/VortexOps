<?php

namespace Tests\Feature\Shows;

use App\Jobs\NotifyShowReconciled;
use App\Jobs\SendLowStockNotification;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\DeductionApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeductionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeductionApprovalService $service;
    private User $user;
    private Show $show;
    private Streamer $streamer;
    private InventoryItem $item;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->service  = app(DeductionApprovalService::class);
        $this->user     = User::factory()->create();
        $this->actingAs($this->user);

        $channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);

        $this->streamer = Streamer::create([
            'name'         => 'Test Streamer',
            'status'       => 'active',
            'payout_type'  => 'flat_rate',
            'flat_rate'    => 100,
            'include_tips' => false,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Test Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 500,
            'whatnot_net'        => 450,
            'tips'               => 10,
            'units_sold'         => 10,
            'show_duration'      => 60,
            'status'             => 'pending_approval',
            'created_by'         => $this->user->id,
        ]);

        $this->location = InventoryLocation::create([
            'name'   => 'Main Storage',
            'type'   => 'main_storage',
            'status' => 'active',
        ]);

        $this->item = InventoryItem::create([
            'name'         => 'Test Cards',
            'unit_cost'    => 5.00,
            'average_cost' => 5.00,
            'is_active'    => true,
        ]);
    }

    private function seedStock(int $quantity): void
    {
        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => $quantity,
        ]);
    }

    private function makeRequest(array $lines = []): DeductionRequest
    {
        $request = DeductionRequest::create([
            'show_id'     => $this->show->id,
            'streamer_id' => $this->streamer->id,
            'status'      => 'approved',
        ]);

        foreach ($lines as $line) {
            DeductionRequestLine::create(array_merge([
                'deduction_request_id'   => $request->id,
                'inventory_item_id'      => $this->item->id,
                'inventory_location_id'  => $this->location->id,
                'quantity_suggested'     => 5,
                'quantity_approved'      => 5,
                'unit_cost_snapshot'     => 5.00,
                'line_total'             => 25.00,
                'raw_description'        => 'Test item',
                'ai_confidence'          => 'high',
            ], $line));
        }

        return $request->load('lines');
    }

    public function test_approve_deducts_stock_and_creates_movement(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([['quantity_approved' => 5]]);

        $this->service->approve($request);

        $stock = InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $this->location->id)
            ->value('quantity');

        $this->assertEquals(15, (float) $stock);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $this->item->id,
            'from_location_id'  => $this->location->id,
            'quantity'          => 5,
            'movement_type'     => 'sale_deduction',
            'reference_type'    => 'deduction_request',
            'reference_id'      => $request->id,
        ]);
    }

    public function test_approve_marks_request_as_processed(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([['quantity_approved' => 5]]);

        $this->service->approve($request);

        $request->refresh();
        $this->assertEquals('processed', $request->status);
        $this->assertEquals($this->user->id, $request->approved_by);
        $this->assertEquals($this->user->id, $request->processed_by);
        $this->assertNotNull($request->approved_at);
        $this->assertNotNull($request->processed_at);
    }

    public function test_approve_marks_show_as_reconciled(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([['quantity_approved' => 5]]);

        $this->service->approve($request);

        $this->show->refresh();
        $this->assertEquals('reconciled', $this->show->status);
    }

    public function test_approve_recalculates_line_total(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([[
            'quantity_approved'  => 3,
            'unit_cost_snapshot' => 10.00,
            'line_total'         => 999.00, // stale value, should be overwritten
        ]]);

        $this->service->approve($request);

        $this->assertDatabaseHas('deduction_request_lines', [
            'deduction_request_id' => $request->id,
            'line_total'           => 30.00, // 3 × 10.00
        ]);
    }

    public function test_approve_skips_lines_with_zero_approved_qty(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([[
            'quantity_approved' => 0,
        ]]);

        $this->service->approve($request);

        $this->assertEquals(
            0,
            InventoryMovement::where('reference_type', 'deduction_request')
                ->where('reference_id', $request->id)
                ->count()
        );
    }

    public function test_approve_handles_multiple_lines(): void
    {
        $item2     = InventoryItem::create(['name' => 'Cards B', 'unit_cost' => 3.00, 'average_cost' => 3.00, 'is_active' => true]);
        $location2 = InventoryLocation::create(['name' => 'Secondary', 'type' => 'other', 'status' => 'active']);

        InventoryStock::create(['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->location->id, 'quantity' => 20]);
        InventoryStock::create(['inventory_item_id' => $item2->id, 'inventory_location_id' => $location2->id, 'quantity' => 10]);

        $request = DeductionRequest::create(['show_id' => $this->show->id, 'streamer_id' => $this->streamer->id, 'status' => 'approved']);

        DeductionRequestLine::create([
            'deduction_request_id'  => $request->id,
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity_suggested'    => 5,
            'quantity_approved'     => 5,
            'unit_cost_snapshot'    => 5.00,
            'line_total'            => 25.00,
            'raw_description'       => 'Item A',
            'ai_confidence'         => 'high',
        ]);

        DeductionRequestLine::create([
            'deduction_request_id'  => $request->id,
            'inventory_item_id'     => $item2->id,
            'inventory_location_id' => $location2->id,
            'quantity_suggested'    => 4,
            'quantity_approved'     => 4,
            'unit_cost_snapshot'    => 3.00,
            'line_total'            => 12.00,
            'raw_description'       => 'Item B',
            'ai_confidence'         => 'medium',
        ]);

        $this->service->approve($request->load('lines'));

        $this->assertEquals(15, (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertEquals(6,  (float) InventoryStock::where('inventory_item_id', $item2->id)->value('quantity'));

        $this->assertEquals(
            2,
            InventoryMovement::where('reference_type', 'deduction_request')
                ->where('reference_id', $request->id)
                ->count()
        );
    }

    public function test_approve_rolls_back_on_insufficient_stock(): void
    {
        $this->seedStock(2); // only 2 in stock, but approving 5

        $request = $this->makeRequest([['quantity_approved' => 5]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');

        $this->service->approve($request);

        // Stock unchanged
        $this->assertEquals(2, (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));

        // Request still approved, not processed
        $request->refresh();
        $this->assertEquals('approved', $request->status);
    }

    public function test_approve_dispatches_reconciliation_notification(): void
    {
        $this->seedStock(20);
        $request = $this->makeRequest([['quantity_approved' => 5]]);

        $this->service->approve($request);

        Queue::assertPushed(NotifyShowReconciled::class, fn ($job) => $job->showId === $this->show->id);
    }
}
