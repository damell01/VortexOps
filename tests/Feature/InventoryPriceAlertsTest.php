<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPriceAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_sale_price_change_is_audited(): void
    {
        $product = Product::create([
            'name' => 'Price alert fixture',
            'sku' => 'ALERT-TEST-001',
            'sale_price' => 20,
        ]);
        $product->update(['sale_price' => 15]);

        $this->assertDatabaseHas('inventory_price_change_events', [
            'product_id' => $product->id,
            'new_price' => 15,
        ]);

    }
}
