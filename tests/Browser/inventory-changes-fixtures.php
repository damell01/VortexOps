<?php

require __DIR__.'/mockup-fixtures.php';

\App\Models\Setting::set('inventory_login_popup_enabled', false);
$location = \App\Models\InventoryLocation::create([
    'name' => 'Popup browser main storage', 'type' => 'main_storage', 'status' => 'active',
]);
for ($i = 1; $i <= 24; $i++) {
    $product = \App\Models\Product::create(['name' => "Inventory change fixture $i", 'sku' => "RECENT-UI-$i", 'sale_price' => 20]);
    \App\Models\InventoryStock::create(['inventory_item_id' => $product->id, 'inventory_location_id' => $location->id, 'quantity' => 3]);
    $product->update(['sale_price' => 25]);
}
