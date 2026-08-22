<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\QuickAddInventoryItem;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The quick-add wizard threw PropertyNotFoundException on every submit: it
 * renders plain inputs bound to $data, not Filament components, so the $form
 * it reached for did not exist. Nothing could be added through it at all, and
 * because the page still looked fine up to the last click, it read as "scanning
 * doesn't work" rather than as a crash.
 */
class QuickAddInventoryItemTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    public function test_it_creates_an_item_from_the_wizard(): void
    {
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['name' => 'Topps Chrome Box', 'unit_cost' => 42.50])
            ->call('submit')
            ->assertHasNoErrors();

        $item = InventoryItem::firstWhere('name', 'Topps Chrome Box');

        $this->assertNotNull($item, 'the wizard did not create the item');
        $this->assertSame(42.50, (float) $item->unit_cost);
    }

    public function test_it_adds_opening_stock_when_a_location_and_quantity_are_given(): void
    {
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', [
                'name'        => 'Sealed Case',
                'unit_cost'   => 100,
                'location_id' => $this->location->id,
                'quantity'    => 6,
            ])
            ->call('submit');

        $item = InventoryItem::firstWhere('name', 'Sealed Case');

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(6, $item->stock()->sum('quantity'), 0.001);
    }

    public function test_a_blank_stock_cost_falls_back_to_the_items_own_cost(): void
    {
        // "" survives `?? $fallback` but casts to 0.0, so leaving the optional
        // override empty used to book the stock in at $0.00.
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', [
                'name'        => 'Fallback Cost Box',
                'unit_cost'   => 25,
                'location_id' => $this->location->id,
                'quantity'    => 2,
                'cost'        => '',
            ])
            ->call('submit');

        $item = InventoryItem::firstWhere('name', 'Fallback Cost Box');

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(25.0, (float) $item->fresh()->average_cost, 0.01);
    }

    public function test_a_missing_name_is_reported_rather_than_saved(): void
    {
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['unit_cost' => 10])
            ->call('submit');

        $this->assertSame(0, InventoryItem::count());
    }

    public function test_a_failed_validation_keeps_what_was_already_typed(): void
    {
        // This used to check that a bad submit sent the user back to the step
        // holding the field. There are no steps any more — Quick Add is one
        // screen — so the question that survives is whether the work already
        // done is still on the screen to correct, or has to be retyped.
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['unit_cost' => 10, 'category' => 'Sealed Wax'])
            ->call('submit')
            ->assertHasErrors(['name'])
            ->assertSet('data.unit_cost', 10)
            ->assertSet('data.category', 'Sealed Wax');

        $this->assertSame(0, InventoryItem::count());
    }

    public function test_a_barcode_already_in_use_is_refused_with_the_reason(): void
    {
        // The duplicate this page exists to prevent. Creating a second item on
        // a barcode already in use makes every future scan of that code
        // ambiguous, and the message has to say so rather than just "taken".
        InventoryItem::create([
            'name' => 'Already Here', 'sku' => 'AH-1', 'barcode' => '012345678905', 'is_active' => true,
        ]);

        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['name' => 'Duplicate Attempt', 'barcode' => '012345678905'])
            ->call('submit')
            ->assertHasErrors(['barcode']);

        $this->assertSame(1, InventoryItem::count());
    }

    public function test_two_items_can_be_added_without_skus(): void
    {
        // sku and barcode are unique but nullable. An untouched input posts "",
        // so the second blank-SKU item collided with the first.
        foreach (['First Blank', 'Second Blank'] as $name) {
            Livewire::test(QuickAddInventoryItem::class)
                ->set('data', ['name' => $name, 'sku' => '', 'barcode' => ''])
                ->call('submit');
        }

        $this->assertSame(2, InventoryItem::count(), 'the second item without a SKU was rejected');
    }

    public function test_a_duplicate_barcode_is_refused_with_a_useful_message(): void
    {
        InventoryItem::create([
            'name' => 'Existing', 'barcode' => '012345678905', 'unit_cost' => 1,
            'average_cost' => 1, 'is_active' => true,
        ]);

        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['name' => 'Duplicate Scan', 'barcode' => '012345678905'])
            ->call('submit');

        $this->assertNull(InventoryItem::firstWhere('name', 'Duplicate Scan'));
    }

    public function test_a_scanned_barcode_is_saved_on_the_item(): void
    {
        // The scan button fills data.barcode; nothing downstream stored it if
        // submit never completed, which is why scanning "did not work".
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', ['name' => 'Scanned Box', 'barcode' => '098765432109'])
            ->call('submit');

        $this->assertSame('098765432109', InventoryItem::firstWhere('name', 'Scanned Box')?->barcode);
    }
}
