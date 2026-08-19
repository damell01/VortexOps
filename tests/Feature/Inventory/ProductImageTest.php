<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A picture of the product, and what stands in when there isn't one.
 *
 * Stock is identified off a shelf by sight long before anyone reads a SKU, and
 * card product names are near-identical by design, so the thumbnail is doing
 * real work in any list of them.
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );
    }

    public function test_an_uploaded_photo_is_served_from_the_disk_it_was_written_to(): void
    {
        // The bug this pins is the one that ate every pallet photo: the app's
        // default disk is `local`, which the public symlink cannot see, so an
        // upload field that does not name its disk saves fine and then 404s.
        Storage::fake(Product::IMAGE_DISK);

        $item = InventoryItem::create([
            'name'       => 'Photographed Box',
            'sku'        => 'PB-1',
            'image_path' => 'products/box.jpg',
            'is_active'  => true,
        ]);

        $this->assertTrue($item->hasImage());
        $this->assertStringContainsString('products/box.jpg', $item->imageUrl());
    }

    public function test_an_item_with_no_photo_falls_back_to_the_site_logo(): void
    {
        $item = InventoryItem::create(['name' => 'Plain', 'sku' => 'PL-1', 'is_active' => true]);

        $this->assertFalse($item->hasImage());
        $this->assertSame(Product::placeholderImageUrl(), $item->imageUrl());
        $this->assertNotNull($item->imageUrl(), 'A list with a hole in it is worse than a brand mark.');
    }

    public function test_the_placeholder_follows_the_configured_logo(): void
    {
        // Deferred to the panel's own resolver rather than deciding again here,
        // so the stand-in is whatever the sidebar is already showing.
        $this->assertStringContainsString('vb-logo', (string) Product::placeholderImageUrl());

        Setting::set('logo_path', 'branding/custom.png');
        Storage::disk('public')->put('branding/custom.png', 'x');

        $this->assertStringContainsString('custom.png', (string) Product::placeholderImageUrl());
    }

    public function test_the_item_form_offers_a_photo_field(): void
    {
        // Asserted against the rendered form rather than the source: grepping
        // the file for "->image()" matched the comment explaining why it is
        // not there, which is the sort of test that only ever tests itself.
        $item = InventoryItem::create(['name' => 'Formy', 'sku' => 'FM-1', 'is_active' => true]);

        $this->get(InventoryItemResource::getUrl('edit', ['record' => $item], panel: 'admin'))
            ->assertOk()
            ->assertSee('Photo')
            ->assertSee('Take Photo');
    }

    public function test_the_stock_repeater_can_name_its_locations(): void
    {
        // The Location box rendered empty for every row: it was bound to
        // 'location.name', which the repeater reads as a nested array key on
        // the stock row rather than as a relationship.
        $item     = InventoryItem::create(['name' => 'Stocked', 'sku' => 'ST-1', 'is_active' => true]);
        $location = InventoryLocation::create(['name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active']);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 20,
        ]);

        $this->get(InventoryItemResource::getUrl('edit', ['record' => $item], panel: 'admin'))
            ->assertOk()
            ->assertSee('Back Room');
    }

    public function test_a_retired_location_still_says_where_the_stock_is(): void
    {
        // Options read every location, not just active ones — stock does not
        // stop existing because somewhere was closed.
        $item     = InventoryItem::create(['name' => 'Stranded', 'sku' => 'SD-1', 'is_active' => true]);
        $location = InventoryLocation::create(['name' => 'Old Bay', 'type' => 'main_storage', 'status' => 'inactive']);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 5,
        ]);

        $this->get(InventoryItemResource::getUrl('edit', ['record' => $item], panel: 'admin'))
            ->assertOk()
            ->assertSee('Old Bay');
    }
}
