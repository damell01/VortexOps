<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalePriceAndMarginTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name'      => 'Booster Box',
            'is_active' => true,
        ], $attributes));
    }

    public function test_margin_is_the_target_less_the_weighted_average_once_stock_has_been_received(): void
    {
        $product = $this->product([
            'unit_cost'    => 50,
            'average_cost' => 42.5,
            'sale_price'   => 92,
        ]);

        // The weighted average wins over the list cost: it is what the stock
        // on the shelf actually cost, and it is what every other costing
        // surface in the app already reports.
        $this->assertSame(42.5, $product->costBasis());
        $this->assertSame(49.5, $product->marginPotential());
        $this->assertSame(53.8, $product->marginPercent());
    }

    public function test_margin_falls_back_to_the_list_cost_before_anything_is_received(): void
    {
        // An item imported from a cost sheet is exactly this: a price and a
        // cost, no receipts. Reporting the whole sale price as margin until
        // someone books a pallet would be worse than reporting nothing.
        $product = $this->product(['unit_cost' => 50, 'average_cost' => 0, 'sale_price' => 92]);

        $this->assertSame(50.0, $product->costBasis());
        $this->assertSame(42.0, $product->marginPotential());
    }

    public function test_margin_is_null_rather_than_zero_when_either_side_is_unknown(): void
    {
        // "No target set" and "sells for what it cost" are different facts,
        // and $0.00 for both hides the one worth acting on.
        $this->assertNull($this->product(['unit_cost' => 50])->marginPotential());
        $this->assertNull($this->product(['sale_price' => 92])->marginPotential());
        $this->assertNull($this->product(['unit_cost' => 50, 'sale_price' => 0])->marginPotential());
    }

    public function test_a_target_below_cost_reports_a_negative_margin(): void
    {
        $product = $this->product(['unit_cost' => 100, 'sale_price' => 80]);

        $this->assertSame(-20.0, $product->marginPotential());
        $this->assertSame(-25.0, $product->marginPercent());
    }

    public function test_margin_on_hand_multiplies_by_what_is_actually_on_the_shelf(): void
    {
        $product  = $this->product(['unit_cost' => 50, 'sale_price' => 92]);
        $location = InventoryLocation::create(['name' => 'Main', 'is_active' => true]);

        InventoryStock::create([
            'inventory_item_id'     => $product->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 6,
        ]);

        $this->assertSame(252.0, $product->fresh()->marginPotentialOnHand());
    }

    public function test_the_target_survives_a_round_trip_at_two_decimals(): void
    {
        $product = $this->product(['sale_price' => 92.499]);

        $this->assertSame('92.50', $product->fresh()->sale_price);
    }

    // ── The list ──────────────────────────────────────────────────────────

    public function test_the_list_carries_both_columns_and_computes_the_margin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $product = $this->product(['unit_cost' => 50, 'sale_price' => 92]);

        Livewire::test(ListInventoryItems::class)
            ->loadTable()
            ->assertTableColumnExists('sale_price')
            ->assertTableColumnExists('margin_potential')
            ->assertTableColumnStateSet('margin_potential', 42.0, $product);
    }

    public function test_missing_a_sale_target_finds_exactly_the_items_with_none(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        // An item with no target is absent from every margin figure rather
        // than showing badly in one, which is how it stays missing.
        $priced   = $this->product(['name' => 'Priced', 'unit_cost' => 50, 'sale_price' => 92]);
        $unpriced = $this->product(['name' => 'Unpriced', 'unit_cost' => 50]);

        Livewire::test(ListInventoryItems::class)
            ->loadTable()
            ->filterTable('no_sale_price')
            ->assertCanSeeTableRecords([$unpriced])
            ->assertCanNotSeeTableRecords([$priced]);
    }

    public function test_thin_margin_uses_the_weighted_average_where_there_is_one(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        // Healthy on list cost, thin once real receipts moved the average —
        // the filter has to read the same cost the margin column does.
        $thin = $this->product([
            'name' => 'Thin', 'unit_cost' => 50, 'average_cost' => 80, 'sale_price' => 100,
        ]);
        $healthy = $this->product([
            'name' => 'Healthy', 'unit_cost' => 50, 'average_cost' => 50, 'sale_price' => 100,
        ]);

        Livewire::test(ListInventoryItems::class)
            ->loadTable()
            ->filterTable('thin_margin')
            ->assertCanSeeTableRecords([$thin])
            ->assertCanNotSeeTableRecords([$healthy]);
    }
}
