<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Putting a code on an item from the list, rather than opening it, finding
 * the field, scanning into it and saving.
 */
class ScanBarcodeOntoItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Booster Box', 'is_active' => true], $attributes));
    }

    public function test_starting_a_scan_opens_the_camera_for_that_item(): void
    {
        $product = $this->product();

        Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->assertSet('barcodeScanTargetId', $product->getKey())
            ->assertDispatched('open-camera-scanner');
    }

    public function test_a_scan_lands_on_the_item_it_was_started_for(): void
    {
        $product = $this->product();
        $other   = $this->product(['name' => 'Something Else']);

        Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->call('saveScannedBarcode', '012345678905');

        $this->assertSame('012345678905', $product->fresh()->barcode);
        $this->assertNull($other->fresh()->barcode);
    }

    public function test_the_capture_is_spent_after_one_scan(): void
    {
        // Otherwise a target left set would catch the next unrelated scan on
        // the page and write it onto whatever was last tapped.
        $product = $this->product();

        $component = Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->call('saveScannedBarcode', '012345678905')
            ->assertSet('barcodeScanTargetId', null);

        $component->call('saveScannedBarcode', '999999999999');

        $this->assertSame('012345678905', $product->fresh()->barcode);
    }

    public function test_a_barcode_already_on_another_item_is_refused_rather_than_thrown(): void
    {
        // The column is uniquely indexed, so without the check the save is a
        // constraint violation and the page 500s on a mis-scan.
        $taken   = $this->product(['name' => 'Already Has It', 'barcode' => '012345678905']);
        $product = $this->product();

        Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->call('saveScannedBarcode', '012345678905')
            ->assertSuccessful();

        $this->assertNull($product->fresh()->barcode);
        $this->assertSame('012345678905', $taken->fresh()->barcode);
    }

    public function test_rescanning_the_same_item_replaces_its_code(): void
    {
        $product = $this->product(['barcode' => '111111111111']);

        Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->call('saveScannedBarcode', '222222222222');

        $this->assertSame('222222222222', $product->fresh()->barcode);
    }

    public function test_an_empty_scan_changes_nothing(): void
    {
        $product = $this->product(['barcode' => '111111111111']);

        Livewire::test(ListInventoryItems::class)
            ->call('startBarcodeScan', $product->getKey())
            ->call('saveScannedBarcode', '   ');

        $this->assertSame('111111111111', $product->fresh()->barcode);
    }

    public function test_a_scan_with_no_capture_in_flight_is_ignored(): void
    {
        $product = $this->product();

        Livewire::test(ListInventoryItems::class)
            ->call('saveScannedBarcode', '012345678905')
            ->assertSuccessful();

        $this->assertNull($product->fresh()->barcode);
    }

    public function test_the_row_action_is_on_the_table(): void
    {
        $product = $this->product();

        Livewire::test(ListInventoryItems::class)
            ->loadTable()
            ->assertTableActionExists('scan_barcode')
            ->assertTableActionVisible('scan_barcode', $product);
    }
}
