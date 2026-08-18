<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\ProductIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flagging a case and a single that answer to the same scanned code.
 *
 * Where such a clash can live is decided by the schema, and it is narrower
 * than it sounds: sku, barcode and upc are each uniquely indexed on products,
 * so no two products can share any of those columns. What is possible is one
 * product carrying a code in its own column while another carries the same
 * value as a product_identity — which is what a vendor code registered against
 * the singles, and printed on the case, looks like.
 *
 * Worth flagging because scanning that code is then ambiguous, and resolving
 * it to the wrong row miscounts by however many units are in the case.
 */
class SharedScanCodeTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes): InventoryItem
    {
        return InventoryItem::create($attributes + ['is_active' => true]);
    }

    public function test_the_schema_forbids_two_products_sharing_a_barcode(): void
    {
        // Stated as a test because it is the reason this looks where it does.
        // If this ever starts passing without an exception, the unique index
        // has gone and same-column clashes become possible.
        $this->product(['sku' => 'A', 'name' => 'A', 'barcode' => '111']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $this->product(['sku' => 'B', 'name' => 'B', 'barcode' => '111']);
    }

    public function test_a_case_and_a_single_answering_to_the_same_code_are_flagged(): void
    {
        $case = $this->product(['sku' => 'CASE-D', 'name' => 'Case D', 'barcode' => '666', 'is_container' => true]);
        $box  = $this->product(['sku' => 'BOX-D', 'name' => 'Box D', 'is_container' => false]);

        // The vendor's code registered against the single rather than typed
        // into its barcode column — the same scan, two meanings.
        ProductIdentity::create(['product_id' => $box->id, 'type' => 'upc', 'value' => '666']);

        $flagged = InventoryItemResource::productsSharingAScanCode();

        $this->assertContains($case->id, $flagged);
        $this->assertContains($box->id, $flagged);
    }

    public function test_two_singles_sharing_a_code_are_not_flagged(): void
    {
        // A duplicate to clean up, not the case/single ambiguity this is for.
        $one = $this->product(['sku' => 'ONE', 'name' => 'One', 'barcode' => '333', 'is_container' => false]);
        $two = $this->product(['sku' => 'TWO', 'name' => 'Two', 'is_container' => false]);

        ProductIdentity::create(['product_id' => $two->id, 'type' => 'upc', 'value' => '333']);

        $this->assertSame([], InventoryItemResource::productsSharingAScanCode());
    }

    public function test_a_case_with_its_own_code_is_not_flagged(): void
    {
        $this->product(['sku' => 'CASE-C', 'name' => 'Case C', 'barcode' => '444', 'is_container' => true]);
        $this->product(['sku' => 'BOX-C', 'name' => 'Box C', 'barcode' => '555', 'is_container' => false]);

        $this->assertSame([], InventoryItemResource::productsSharingAScanCode());
    }

    public function test_the_result_does_not_leak_between_requests(): void
    {
        $this->assertSame([], InventoryItemResource::productsSharingAScanCode());

        $case = $this->product(['sku' => 'CASE-E', 'name' => 'Case E', 'barcode' => '777', 'is_container' => true]);
        $box  = $this->product(['sku' => 'BOX-E', 'name' => 'Box E', 'is_container' => false]);
        ProductIdentity::create(['product_id' => $box->id, 'type' => 'upc', 'value' => '777']);

        // Memoised per request, so within one it is allowed to be stale...
        $this->assertSame([], InventoryItemResource::productsSharingAScanCode());

        // ...but a fresh container must recompute. Held in a static instead,
        // this returned the first answer for the life of the process — which is
        // how it went wrong across tests, and would in a queue worker too.
        app()->forgetInstance('vx.products_sharing_scan_code');

        $this->assertContains($case->id, InventoryItemResource::productsSharingAScanCode());
    }
}
