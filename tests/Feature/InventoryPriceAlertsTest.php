<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryPriceAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPriceAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_changes_are_recorded_and_acknowledged(): void
    {
        $user = User::factory()->create();
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

        $alerts = app(InventoryPriceAlerts::class);
        $pending = $alerts->pending($user);
        $this->assertCount(1, $pending);
        $this->assertTrue($pending[0]['decreased']);

        $alerts->acknowledge($user, $pending[0]['id']);
        $this->assertSame([], $alerts->pending($user));
    }
}
