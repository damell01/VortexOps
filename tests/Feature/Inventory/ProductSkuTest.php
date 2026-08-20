<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every product gets a SKU, however it was created.
 *
 * The create form carried a default, so anything typed in by hand had one and
 * nothing else did. A product created by scanning an unknown barcode at a
 * pallet — which is the normal way stock enters this system — arrived with the
 * column blank and stayed that way, because a form default only fires on the
 * form.
 *
 * Moving it onto the model covers every path that exists and the ones written
 * later, which is the point: the next place a product gets created should not
 * have to remember.
 */
class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_created_directly_gets_one(): void
    {
        $product = Product::create(['name' => 'No SKU Given', 'is_active' => true]);

        $this->assertNotEmpty($product->sku);
    }

    public function test_a_product_created_by_scanning_gets_one(): void
    {
        // The path that had no SKU at all. Scanning an unknown barcode at a
        // pallet creates the product, and nothing in that path went near the
        // form's default.
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $pallet   = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'reference' => 'PO-SKU',
            'status'    => 'staged',
        ]);

        $line = PalletLine::create([
            'pallet_id' => $pallet->id, 'line_number' => 1,
            'description' => 'Mystery Case', 'case_count' => 1,
        ]);

        $result = app(ReceivingService::class)->linkLineByScan($line, '5551234567', $location);

        $this->assertTrue($result['created']);
        $this->assertNotEmpty($result['item']->sku, 'A scanned-in product needs a SKU like any other.');
    }

    public function test_a_sku_that_was_supplied_is_left_alone(): void
    {
        // Generating over the top of a real one would break every scan and
        // label already pointing at it.
        $product = Product::create(['name' => 'Mine', 'sku' => 'CHOSEN-1', 'is_active' => true]);

        $this->assertSame('CHOSEN-1', $product->sku);
    }

    public function test_generated_skus_do_not_collide(): void
    {
        $skus = collect(range(1, 40))
            ->map(fn ($n) => Product::create(['name' => "Item {$n}", 'is_active' => true])->sku);

        $this->assertCount(40, $skus->unique(), 'Two products sharing a SKU makes every scan ambiguous.');
    }

    public function test_a_generated_sku_avoids_one_already_taken(): void
    {
        // Including soft-deleted products: the column is uniquely indexed and a
        // deleted row still occupies its value, so ignoring them would fail on
        // insert rather than pick again.
        $taken = Product::create(['name' => 'Taken', 'is_active' => true]);
        $sku   = $taken->sku;
        $taken->delete();

        $this->assertNotSame($sku, Product::generateSku());
    }

    public function test_the_generator_can_be_called_without_creating_anything(): void
    {
        // The regenerate button on the form uses it, and pressing it should not
        // leave a product behind.
        $before = Product::withTrashed()->count();

        $this->assertNotEmpty(Product::generateSku());
        $this->assertSame($before, Product::withTrashed()->count());
    }
}
