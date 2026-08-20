<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\AddPalletLines;
use App\Filament\Resources\PalletResource\Pages\ViewPallet;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Capture the box while the box is in front of you.
 *
 * Staging happens for things that are not in inventory yet, so a line could
 * only ever hold what the packing slip called it. But the two facts worth
 * having — what its code is, and what it actually looks like — are only free to
 * collect at that moment, standing at the pallet. Recorded later they have to
 * come from memory, against a name off a slip.
 *
 * Neither creates anything in inventory. That is the point of staging, and it
 * is what these lock in.
 */
class StagingScanAndPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'reference' => 'PO-STAGE',
            'status'    => 'staged',
        ]);
    }

    private function staging(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(AddPalletLines::class, ['record' => $this->pallet->id]);
    }

    public function test_a_scanned_code_is_kept_on_the_line(): void
    {
        $this->staging()
            ->set('rows.0.description', 'Mystery Case')
            ->call('scanIntoRow', 0, '  5551234567  ')
            ->call('save');

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertSame('5551234567', $line->barcode, 'It should be trimmed and stored.');
        $this->assertNull($line->inventory_item_id, 'Scanning at staging must not invent a product.');
    }

    public function test_scanning_something_already_stocked_links_it_instead(): void
    {
        // The answer to "is this new?" is in the scan, so asking the person
        // holding the box to also find it in a dropdown is a step worth losing.
        $known = InventoryItem::create(['name' => 'Known Box', 'sku' => 'KB-1', 'barcode' => '4242', 'is_active' => true]);

        $this->staging()
            ->set('rows.0.description', 'Whatever the slip said')
            ->call('scanIntoRow', 0, '4242')
            ->call('save');

        $this->assertSame($known->id, $this->pallet->lines()->firstOrFail()->inventory_item_id);
    }

    public function test_a_scan_names_the_line_when_nothing_was_typed(): void
    {
        InventoryItem::create(['name' => 'Chrome Hobby', 'sku' => 'CH-1', 'barcode' => '777', 'is_active' => true]);

        $this->staging()
            ->call('scanIntoRow', 0, '777')
            ->assertSet('rows.0.description', 'Chrome Hobby');
    }

    public function test_a_scan_does_not_overwrite_a_name_already_typed(): void
    {
        // What somebody wrote down is what they meant; a catalogue name is a
        // suggestion, and silently replacing the first with the second loses
        // the only description that came from looking at the box.
        InventoryItem::create(['name' => 'Catalogue Name', 'sku' => 'CN-1', 'barcode' => '888', 'is_active' => true]);

        $this->staging()
            ->set('rows.0.description', 'What the slip called it')
            ->call('scanIntoRow', 0, '888')
            ->assertSet('rows.0.description', 'What the slip called it');
    }

    public function test_an_empty_scan_is_ignored_rather_than_stored(): void
    {
        $this->staging()
            ->set('rows.0.description', 'Something')
            ->call('scanIntoRow', 0, '   ')
            ->assertSet('rows.0.barcode', '');
    }

    public function test_a_photo_taken_at_staging_is_kept_on_the_line(): void
    {
        Storage::fake(Product::IMAGE_DISK);

        $this->staging()
            ->set('rows.0.description', 'Photographed Box')
            ->set('rows.0.photo', UploadedFile::fake()->image('box.jpg'))
            ->call('save');

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertTrue($line->hasPhoto());
        Storage::disk(Product::IMAGE_DISK)->assertExists($line->photo_path);
    }

    public function test_saving_again_keeps_the_photo(): void
    {
        // Editing a line's cost should not quietly discard the picture taken
        // when the pallet was staged.
        Storage::fake(Product::IMAGE_DISK);

        $this->staging()
            ->set('rows.0.description', 'Photographed Box')
            ->set('rows.0.photo', UploadedFile::fake()->image('box.jpg'))
            ->call('save');

        $before = $this->pallet->lines()->firstOrFail()->photo_path;

        Livewire::test(AddPalletLines::class, ['record' => $this->pallet->id])
            ->set('rows.0.unit_cost', 12.5)
            ->call('save');

        $this->assertSame($before, $this->pallet->lines()->firstOrFail()->photo_path);
    }

    public function test_the_photo_becomes_the_products_image_when_it_is_created(): void
    {
        // The staging photo is of this box, taken by us. It is a better picture
        // of the product than anything added later, and the only one that
        // exists at the moment the product is created.
        Storage::fake(Product::IMAGE_DISK);
        Setting::set('default_receiving_location_id', (string) $this->location->id);

        $this->staging()
            ->set('rows.0.description', 'Brand New Thing')
            ->set('rows.0.photo', UploadedFile::fake()->image('box.jpg'))
            ->call('scanIntoRow', 0, '9090909090')
            ->call('save');

        $line = $this->pallet->lines()->firstOrFail();

        Livewire::test(ViewPallet::class, ['record' => $this->pallet->id])
            ->call('useStagedScan', $line->id);

        $product = $line->fresh()->inventoryItem;

        $this->assertNotNull($product, 'The staged scan should have created it.');
        $this->assertSame($line->photo_path, $product->image_path);
        $this->assertSame('9090909090', $product->barcode);
    }

    public function test_a_staged_scan_can_be_used_without_the_camera_again(): void
    {
        // Pointing the camera at the same box on arrival asks a question that
        // was answered while staging.
        Setting::set('default_receiving_location_id', (string) $this->location->id);

        $this->staging()
            ->set('rows.0.description', 'Scanned At Staging')
            ->call('scanIntoRow', 0, '1212121212')
            ->call('save');

        $line = $this->pallet->lines()->firstOrFail();

        Livewire::test(ViewPallet::class, ['record' => $this->pallet->id])
            ->call('useStagedScan', $line->id);

        $line->refresh();

        $this->assertNotNull($line->inventory_item_id);
        $this->assertSame(1, $line->receivedCases(), 'The box being confirmed is a case received.');
    }

    public function test_a_line_with_no_staged_scan_is_refused_rather_than_guessed(): void
    {
        $line = $this->pallet->lines()->create([
            'line_number' => 1, 'description' => 'Never Scanned', 'case_count' => 1,
        ]);

        Livewire::test(ViewPallet::class, ['record' => $this->pallet->id])
            ->call('useStagedScan', $line->id);

        $this->assertNull($line->fresh()->inventory_item_id);
    }
}
