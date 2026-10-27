<?php
// Run only after migrations in the isolated browser-test database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_contains(config('database.connections.sqlite.database'), 'inventory-audit.sqlite')) {
    throw new RuntimeException('This fixture requires the isolated inventory-audit SQLite database.');
}
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AdminModules;
use Spatie\Permission\Models\Role;
$role = Role::findOrCreate('admin', 'web');
$user = User::firstOrCreate(['email' => config('app.owner_email')], ['name' => 'Inventory Browser Audit', 'password' => bcrypt('inventory-audit-local'), 'email_verified_at' => now()]);
$user->assignRole($role);
Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
$source = InventoryLocation::firstOrCreate(['name' => 'Audit Main'], ['status' => 'active', 'type' => 'main_storage']);
$destination = InventoryLocation::firstOrCreate(['name' => 'Audit Back'], ['status' => 'active', 'type' => 'main_storage']);
$item = InventoryItem::firstOrCreate(['sku' => 'BROWSER-AUDIT'], ['name' => 'Browser Audit Box', 'barcode' => '012345678905', 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true]);
InventoryStock::firstOrCreate(['inventory_item_id' => $item->id, 'inventory_location_id' => $source->id], ['quantity' => 100]);
$vendor = Vendor::firstOrCreate(['name' => 'Audit Vendor'], ['status' => 'active']);
$pallet = Pallet::firstOrCreate(['reference' => 'BROWSER-RECEIVE'], ['vendor_id' => $vendor->id, 'status' => 'pending']);
$line = PalletLine::firstOrCreate(['pallet_id' => $pallet->id, 'line_number' => 1], ['description' => $item->name, 'inventory_item_id' => $item->id, 'inventory_location_id' => $source->id, 'case_count' => 20, 'quantity_per_case' => 1, 'unit_cost' => 10]);
file_put_contents(storage_path('framework/testing/inventory-audit-fixture.json'), json_encode(['email' => $user->email, 'item' => $item->id, 'source' => $source->id, 'destination' => $destination->id, 'pallet' => $pallet->id, 'line' => $line->id, 'stockPath' => ltrim(parse_url(\App\Filament\Resources\InventoryStockResource::getUrl('index'), PHP_URL_PATH), '/')]));
echo "Isolated inventory browser fixtures ready.\n";
