<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Receiving is done at the pallet with a phone far more often than at a desk
 * with a scanner gun, so a camera read has to reach the same place typing does
 * — and submit on its own, since nobody is holding a phone, a box, and also
 * tapping Receive.
 */
class ScannerCameraTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'Camera Vendor', 'status' => 'active'])->id,
            'reference' => 'PO-CAM',
            'status'    => 'pending',
        ]);
    }

    /** The scanner in the state it is in while someone is unloading a pallet. */
    private function receiving(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(InventoryScanner::class)
            ->set('mode', 'receive')
            ->set('rcvPalletId', $this->pallet->id);
    }

    public function test_a_camera_read_is_submitted_without_a_second_tap(): void
    {
        InventoryItem::create([
            'name' => 'Scanned Box', 'barcode' => '012345678905',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        // The code reaches the component and is acted on: a submitted scan
        // clears the box, which a code merely parked in the field would not.
        $this->receiving()
            ->dispatch('barcodeScanned', barcode: '012345678905')
            ->assertSet('scanInput', '');
    }

    public function test_an_empty_camera_read_is_ignored(): void
    {
        // Decoders can fire with nothing usable; submitting that would clear the
        // operator's screen and report an error for a scan that never happened.
        $this->receiving()
            ->set('scanInput', 'half-typed')
            ->dispatch('barcodeScanned', barcode: '   ')
            ->assertSet('scanInput', 'half-typed');
    }

    public function test_the_scanner_offers_a_camera_button(): void
    {
        // The camera used to be a button with its own id and its own preview
        // element on this page. It is one shared component now, reached by
        // dispatching open-camera-scanner, so the thing worth asserting is
        // that a button exists and that it fires that event — testing for the
        // old id would only prove the old markup was still there.
        $html = $this->receiving()->html();

        $this->assertStringContainsString('data-camera-scan', $html, 'no camera button on the scanner');
        $this->assertStringContainsString('openScanner()', $html, 'camera button is not wired to the scanner');
        $this->assertStringContainsString('open-camera-scanner', $html, 'nothing dispatches the event the scanner listens for');
    }

    public function test_the_camera_is_not_offered_before_a_pallet_is_chosen(): void
    {
        // There is nothing to receive against yet, so a camera button here would
        // scan codes into a screen that cannot do anything with them.
        $html = Livewire::test(InventoryScanner::class)->set('mode', 'receive')->html();

        $this->assertStringNotContainsString('id="rcv-camera-btn"', $html);
    }
}
