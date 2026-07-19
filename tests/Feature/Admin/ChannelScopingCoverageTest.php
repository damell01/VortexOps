<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ProductInsights;
use App\Filament\Pages\ShowStatusBoard;
use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\InventoryMovementResource;
use App\Filament\Resources\InventoryStockResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The channel switcher is only useful if every list actually respects it.
 * Several resources had a channel-aware Eloquent scope defined on their model
 * (scopeInChannelContext) that was never actually called from
 * getEloquentQuery() — so switching to a specific channel silently kept
 * showing every channel's data. Covers the resources (and the two raw-query
 * pages) that were fixed to call it.
 */
class ChannelScopingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channelA;
    private WhatnotChannel $channelB;
    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        $this->creator  = User::factory()->create();
        $this->channelA = WhatnotChannel::create(['name' => 'Channel A', 'status' => 'active']);
        $this->channelB = WhatnotChannel::create(['name' => 'Channel B', 'status' => 'active']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function makeShow(WhatnotChannel $channel, array $attrs = []): Show
    {
        return Show::create(array_merge([
            'title'              => 'Show for ' . $channel->name,
            'show_date'          => now()->toDateString(),
            'status'             => 'reconciled',
            'created_by'         => $this->creator->id,
            'whatnot_channel_id' => $channel->id,
        ], $attrs));
    }

    // ── ShowResource ─────────────────────────────────────────────────────────

    public function test_show_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $showA = $this->makeShow($this->channelA);
        $showB = $this->makeShow($this->channelB);

        ChannelContext::setActive($this->channelA->id);
        $ids = ShowResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($showA->id));
        $this->assertFalse($ids->contains($showB->id));

        ChannelContext::setActive(null);
        $ids = ShowResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($showA->id));
        $this->assertTrue($ids->contains($showB->id));
    }

    // ── PayoutResource ───────────────────────────────────────────────────────

    public function test_payout_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $payoutA = Payout::create(['streamer_id' => $streamer->id, 'payout_type' => 'flat_rate', 'whatnot_channel_id' => $this->channelA->id]);
        $payoutB = Payout::create(['streamer_id' => $streamer->id, 'payout_type' => 'flat_rate', 'whatnot_channel_id' => $this->channelB->id]);

        ChannelContext::setActive($this->channelA->id);
        $ids = PayoutResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($payoutA->id));
        $this->assertFalse($ids->contains($payoutB->id));

        ChannelContext::setActive(null);
        $ids = PayoutResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($payoutA->id));
        $this->assertTrue($ids->contains($payoutB->id));
    }

    // ── DeductionRequestResource (scoped via its show) ──────────────────────

    public function test_deduction_request_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $showA = $this->makeShow($this->channelA);
        $showB = $this->makeShow($this->channelB);
        $reqA = DeductionRequest::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        $reqB = DeductionRequest::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        ChannelContext::setActive($this->channelA->id);
        $ids = DeductionRequestResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($reqA->id));
        $this->assertFalse($ids->contains($reqB->id));

        ChannelContext::setActive(null);
        $ids = DeductionRequestResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($reqA->id));
        $this->assertTrue($ids->contains($reqB->id));
    }

    // ── StreamerLogResource (scoped via its show) ───────────────────────────

    public function test_streamer_log_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $showA = $this->makeShow($this->channelA);
        $showB = $this->makeShow($this->channelB);
        $logA = StreamerLogEntry::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        $logB = StreamerLogEntry::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        ChannelContext::setActive($this->channelA->id);
        $ids = StreamerLogResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($logA->id));
        $this->assertFalse($ids->contains($logB->id));

        ChannelContext::setActive(null);
        $ids = StreamerLogResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($logA->id));
        $this->assertTrue($ids->contains($logB->id));
    }

    // ── InventoryStockResource / InventoryMovementResource (scoped via location) ─

    private function makeLocation(WhatnotChannel $channel): InventoryLocation
    {
        return InventoryLocation::create(['name' => 'Loc ' . $channel->name, 'type' => 'main_storage', 'status' => 'active', 'whatnot_channel_id' => $channel->id]);
    }

    public function test_inventory_stock_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $item = InventoryItem::create(['sku' => 'SKU1', 'name' => 'Item', 'unit_cost' => 1, 'is_active' => true]);
        $locA = $this->makeLocation($this->channelA);
        $locB = $this->makeLocation($this->channelB);
        $stockA = InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $locA->id, 'quantity' => 5]);
        $stockB = InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $locB->id, 'quantity' => 5]);

        ChannelContext::setActive($this->channelA->id);
        $ids = InventoryStockResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($stockA->id));
        $this->assertFalse($ids->contains($stockB->id));

        ChannelContext::setActive(null);
        $ids = InventoryStockResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($stockA->id));
        $this->assertTrue($ids->contains($stockB->id));
    }

    public function test_inventory_movement_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $item = InventoryItem::create(['sku' => 'SKU2', 'name' => 'Item2', 'unit_cost' => 1, 'is_active' => true]);
        $locA = $this->makeLocation($this->channelA);
        $locB = $this->makeLocation($this->channelB);
        $moveA = InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $locA->id, 'quantity' => 1, 'movement_type' => 'opening']);
        $moveB = InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $locB->id, 'quantity' => 1, 'movement_type' => 'opening']);

        ChannelContext::setActive($this->channelA->id);
        $ids = InventoryMovementResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($moveA->id));
        $this->assertFalse($ids->contains($moveB->id));

        ChannelContext::setActive(null);
        $ids = InventoryMovementResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($moveA->id));
        $this->assertTrue($ids->contains($moveB->id));
    }

    // ── WhatnotLedgerResource ────────────────────────────────────────────────

    public function test_whatnot_ledger_resource_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $entryA = WhatnotLedgerEntry::create(['whatnot_channel_id' => $this->channelA->id, 'created_date' => now()->toDateString(), 'amount' => 10, 'type' => 'sale']);
        $entryB = WhatnotLedgerEntry::create(['whatnot_channel_id' => $this->channelB->id, 'created_date' => now()->toDateString(), 'amount' => 10, 'type' => 'sale']);

        ChannelContext::setActive($this->channelA->id);
        $ids = WhatnotLedgerResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($entryA->id));
        $this->assertFalse($ids->contains($entryB->id));

        ChannelContext::setActive(null);
        $ids = WhatnotLedgerResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($entryA->id));
        $this->assertTrue($ids->contains($entryB->id));
    }

    // ── ShowStatusBoard (Kanban page) ────────────────────────────────────────

    public function test_show_status_board_scopes_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $showA = $this->makeShow($this->channelA, ['status' => 'pending_review']);
        $showB = $this->makeShow($this->channelB, ['status' => 'pending_review']);

        ChannelContext::setActive($this->channelA->id);
        $board = new ShowStatusBoard();
        $columns = $board->getColumns();
        $allShowIds = collect($columns)->flatMap(fn ($c) => $c['shows']->pluck('id'));
        $this->assertTrue($allShowIds->contains($showA->id));
        $this->assertFalse($allShowIds->contains($showB->id));

        ChannelContext::setActive(null);
        $columns = $board->getColumns();
        $allShowIds = collect($columns)->flatMap(fn ($c) => $c['shows']->pluck('id'));
        $this->assertTrue($allShowIds->contains($showA->id));
        $this->assertTrue($allShowIds->contains($showB->id));
    }

    // ── ProductInsights (raw query builder page) ────────────────────────────

    public function test_product_insights_scopes_units_sold_by_active_channel(): void
    {
        $this->actingAs($this->owner());
        $item = InventoryItem::create(['sku' => 'SKU3', 'name' => 'Widget', 'unit_cost' => 1, 'is_active' => true]);
        $showA = $this->makeShow($this->channelA);
        $showB = $this->makeShow($this->channelB);

        \App\Models\WhatnotShowOrder::create([
            'show_id' => $showA->id, 'inventory_item_id' => $item->id,
            'whatnot_order_id' => 'A1', 'quantity' => 3, 'total_price' => 30, 'total_cost' => 9,
            'show_date' => now()->toDateString(),
        ]);
        \App\Models\WhatnotShowOrder::create([
            'show_id' => $showB->id, 'inventory_item_id' => $item->id,
            'whatnot_order_id' => 'B1', 'quantity' => 7, 'total_price' => 70, 'total_cost' => 21,
            'show_date' => now()->toDateString(),
        ]);

        ChannelContext::setActive($this->channelA->id);
        $page = new ProductInsights();
        $page->view = 'all';
        $row = $page->rows->firstWhere('id', $item->id);
        $this->assertSame(3, (int) $row['units_sold']);

        ChannelContext::setActive(null);
        $page = new ProductInsights();
        $page->view = 'all';
        $row = $page->rows->firstWhere('id', $item->id);
        $this->assertSame(10, (int) $row['units_sold']);
    }
}
