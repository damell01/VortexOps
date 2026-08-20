<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\ViewPallet;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photograph an item while the box is in your hands.
 *
 * This was first built onto the staging table, which was wrong: staging happens
 * from the packing slip, before the pallet lands, so there is nothing there to
 * point a camera at. The only moment a real photo is free is while the pallet is
 * being unloaded — the box is open, the line has just been scanned, and the
 * product it created has no picture and no catalogue to get one from.
 *
 * So it lives on the pallet row, and only once the line is linked: until then
 * there is no product for the picture to belong to.
 */
class ReceivingPhotoTest extends TestCase
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
            'status'    => 'receiving',
        ]);

        Storage::fake(InventoryItem::IMAGE_DISK);
    }

    private function linkedLine(array $itemAttributes = []): PalletLine
    {
        $item = InventoryItem::create(array_merge([
            'name' => 'Chrome Box', 'sku' => 'CHR-1', 'is_active' => true,
        ], $itemAttributes));

        return $this->pallet->lines()->create([
            'line_number'           => 1,
            'description'           => 'Chrome Box',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 2,
        ]);
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ViewPallet::class, ['record' => $this->pallet->id]);
    }

    public function test_a_photo_taken_at_the_pallet_becomes_the_products_picture(): void
    {
        $line = $this->linkedLine();

        $this->page()->set('linePhotos.' . $line->id, UploadedFile::fake()->image('box.jpg'));

        $item = $line->inventoryItem->fresh();

        $this->assertTrue($item->hasImage());
        Storage::disk(InventoryItem::IMAGE_DISK)->assertExists($item->image_path);
    }

    public function test_replacing_a_photo_removes_the_one_it_replaced(): void
    {
        // The old file is nothing's picture any more, and leaving it behind
        // fills the disk with images no page will ever ask for.
        $line = $this->linkedLine();

        $this->page()->set('linePhotos.' . $line->id, UploadedFile::fake()->image('first.jpg'));
        $first = $line->inventoryItem->fresh()->image_path;

        $this->page()->set('linePhotos.' . $line->id, UploadedFile::fake()->image('second.jpg'));
        $second = $line->inventoryItem->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk(InventoryItem::IMAGE_DISK)->assertMissing($first);
        Storage::disk(InventoryItem::IMAGE_DISK)->assertExists($second);
    }

    public function test_a_line_with_nothing_linked_yet_has_nothing_to_photograph(): void
    {
        // There is no product for the picture to belong to, and inventing one
        // to hold it is what linking the line is for.
        $line = $this->pallet->lines()->create([
            'line_number' => 1, 'description' => 'Not linked', 'case_count' => 1,
        ]);

        $this->page()->set('linePhotos.' . $line->id, UploadedFile::fake()->image('box.jpg'));

        $this->assertNull($line->fresh()->inventory_item_id);
        $this->assertSame([], Storage::disk(InventoryItem::IMAGE_DISK)->allFiles());
    }

    public function test_a_photo_aimed_at_another_pallets_line_is_refused(): void
    {
        // Resolved through this pallet's own lines, so a stale page cannot
        // rewrite the picture of a product it is not looking at.
        $other = Pallet::create([
            'vendor_id' => $this->pallet->vendor_id, 'reference' => 'PO-ELSEWHERE', 'status' => 'receiving',
        ]);

        $item = InventoryItem::create(['name' => 'Not Yours', 'sku' => 'NY-1', 'is_active' => true]);

        $foreign = $other->lines()->create([
            'line_number'           => 1,
            'description'           => 'Not yours',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 1,
        ]);

        $this->page()->set('linePhotos.' . $foreign->id, UploadedFile::fake()->image('box.jpg'));

        $this->assertFalse($item->fresh()->hasImage());
    }

    public function test_the_upload_is_not_left_holding_the_row(): void
    {
        // Livewire refuses a second file on a key that still holds the first,
        // so working down a pallet would photograph one item and then silently
        // stop.
        $line = $this->linkedLine();

        $this->page()
            ->set('linePhotos.' . $line->id, UploadedFile::fake()->image('box.jpg'))
            ->assertSet('linePhotos.' . $line->id, null);
    }
}
