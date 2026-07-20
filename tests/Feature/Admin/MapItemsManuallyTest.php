<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Models\DeductionRequestLine;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Map Items Manually" used to group Whatnot orders by item_name and skip
 * anything with an empty one — but Whatnot often has no real item
 * description at all, just a generic per-lot label ("Item #123"). Any order
 * with a blank item_name was silently dropped instead of getting a line.
 * Grouped by lot_number instead — the identifier Whatnot actually gives
 * reliably — with item_name kept only as an extra label when it adds real
 * information over the lot number itself.
 */
class MapItemsManuallyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u->assignRole('admin');

        return $u;
    }

    private function makeShow(): Show
    {
        $creator = User::factory()->create();
        $streamer = \App\Models\Streamer::create(['name' => 'S', 'status' => 'active']);

        $show = Show::create([
            'title' => 'S', 'show_date' => now()->toDateString(),
            'status' => 'pending_review', 'created_by' => $creator->id,
        ]);
        $show->streamers()->attach($streamer);

        return $show;
    }

    public function test_orders_with_no_item_name_still_get_a_line(): void
    {
        $this->actingAs($this->admin());
        $show = $this->makeShow();

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A1',
            'lot_number' => 123, 'item_name' => null,
            'quantity' => 2, 'total_price' => 40, 'total_cost' => 10,
            'show_date' => now()->toDateString(),
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->callAction('map_manually')
            ->assertHasNoActionErrors();

        $line = DeductionRequestLine::first();
        $this->assertNotNull($line, 'An order with no item_name must still produce a deduction line.');
        $this->assertSame('Lot #123', $line->raw_description);
        $this->assertSame(2.0, (float) $line->quantity_suggested);
    }

    public function test_orders_with_the_same_lot_number_are_grouped_into_one_line(): void
    {
        $this->actingAs($this->admin());
        $show = $this->makeShow();

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A1',
            'lot_number' => 5, 'item_name' => null,
            'quantity' => 1, 'total_price' => 20, 'total_cost' => 5,
            'show_date' => now()->toDateString(),
        ]);
        WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A2',
            'lot_number' => 5, 'item_name' => null,
            'quantity' => 3, 'total_price' => 60, 'total_cost' => 15,
            'show_date' => now()->toDateString(),
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->callAction('map_manually');

        $this->assertSame(1, DeductionRequestLine::count());
        $this->assertSame(4.0, (float) DeductionRequestLine::first()->quantity_suggested);
    }

    public function test_generic_item_name_restating_the_lot_number_is_not_duplicated_in_the_label(): void
    {
        $this->actingAs($this->admin());
        $show = $this->makeShow();

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A1',
            'lot_number' => 42, 'item_name' => 'Item #42',
            'quantity' => 1, 'total_price' => 20, 'total_cost' => 5,
            'show_date' => now()->toDateString(),
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->callAction('map_manually');

        $this->assertSame('Lot #42', DeductionRequestLine::first()->raw_description);
    }

    public function test_a_real_item_name_is_kept_alongside_the_lot_number(): void
    {
        $this->actingAs($this->admin());
        $show = $this->makeShow();

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A1',
            'lot_number' => 7, 'item_name' => '2024 Topps Chrome Hobby Box',
            'quantity' => 1, 'total_price' => 20, 'total_cost' => 5,
            'show_date' => now()->toDateString(),
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->callAction('map_manually');

        $this->assertSame('Lot #7 — 2024 Topps Chrome Hobby Box', DeductionRequestLine::first()->raw_description);
    }

    public function test_orders_with_neither_lot_number_nor_item_name_each_get_their_own_line(): void
    {
        $this->actingAs($this->admin());
        $show = $this->makeShow();

        $orderA = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A1',
            'lot_number' => null, 'item_name' => null,
            'quantity' => 1, 'total_price' => 20, 'total_cost' => 5,
            'show_date' => now()->toDateString(),
        ]);
        $orderB = WhatnotShowOrder::create([
            'show_id' => $show->id, 'whatnot_order_id' => 'A2',
            'lot_number' => null, 'item_name' => null,
            'quantity' => 1, 'total_price' => 20, 'total_cost' => 5,
            'show_date' => now()->toDateString(),
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->callAction('map_manually');

        $descriptions = DeductionRequestLine::pluck('raw_description')->all();
        $this->assertContains("Order #{$orderA->id}", $descriptions);
        $this->assertContains("Order #{$orderB->id}", $descriptions);
        $this->assertCount(2, $descriptions);
    }
}
