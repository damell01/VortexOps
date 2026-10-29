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
$admin = User::firstOrCreate(['email' => 'inventory-audit-admin@example.test'], ['name' => 'Audit Admin', 'password' => bcrypt('inventory-audit-local'), 'email_verified_at' => now()]);
$admin->assignRole('admin');
Role::findOrCreate('streamer', 'web');
$reader = User::firstOrCreate(['email' => 'inventory-audit-streamer@example.test'], ['name' => 'Audit Streamer', 'password' => bcrypt('inventory-audit-local'), 'email_verified_at' => now()]);
$reader->assignRole('streamer');
$readerProfile = \App\Models\Streamer::firstOrCreate(['user_id' => $reader->id], ['name' => 'Audit Streamer', 'status' => 'active']);
$readerLocation = InventoryLocation::firstOrCreate(['streamer_id' => $readerProfile->id], ['name' => 'Audit Personal Shelf', 'type' => 'streamer_inventory', 'status' => 'active']);
InventoryStock::firstOrCreate(['inventory_item_id' => $item->id, 'inventory_location_id' => $readerLocation->id], ['quantity' => 5]);
$pallets = ['desktop' => $pallet->id];
foreach (['mobile', 'safari-mobile'] as $browser) {
    $delivery = Pallet::firstOrCreate(['reference' => 'BROWSER-RECEIVE-'.$browser], ['vendor_id' => $vendor->id, 'status' => 'pending']);
    PalletLine::firstOrCreate(['pallet_id' => $delivery->id, 'line_number' => 1], ['description' => $item->name, 'inventory_item_id' => $item->id, 'inventory_location_id' => $source->id, 'case_count' => 20, 'quantity_per_case' => 1, 'unit_cost' => 10]);
    $pallets[$browser] = $delivery->id;
}
$deleteItems = [];
foreach (['desktop', 'mobile', 'safari-mobile'] as $project) {
    $disposable = InventoryItem::firstOrCreate(['sku' => 'DELETE-DETAIL-'.$project], ['name' => 'Delete Detail '.$project, 'is_active' => true]);
    $deleteItems[$project] = $disposable->id;
}
$assistant = User::firstOrCreate(['email' => 'inventory-audit-assistant@example.test'], ['name' => 'Assistant Browser Audit', 'password' => bcrypt('inventory-audit-local'), 'email_verified_at' => now()]);
$assistant->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
$assistantBusiness = \App\Models\Business::firstOrCreate(['name' => 'Assistant Browser Audit Business']);
$assistantProfile = \App\Models\Streamer::firstOrCreate(['user_id' => $assistant->id], ['business_id' => $assistantBusiness->id, 'name' => 'Assistant Browser Audit', 'member_type' => 'assistant', 'payout_type' => 'hourly', 'hourly_rate' => 17, 'status' => 'active']);
$assistantPaid = \App\Models\WeeklyPayoutBatch::create(['business_id' => $assistantBusiness->id, 'week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'paid']);
$assistantDraft = \App\Models\WeeklyPayoutBatch::create(['business_id' => $assistantBusiness->id, 'week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft']);
foreach ([[$assistantPaid, 'paid', 125.25], [$assistantDraft, 'draft', 876.54]] as [$run, $status, $amount]) {
    \App\Models\Payout::create(['streamer_id' => $assistantProfile->id, 'weekly_payout_batch_id' => $run->id, 'status' => $status, 'payout_type' => 'hourly', 'calculated_payout' => $amount]);
}
file_put_contents(storage_path('framework/testing/inventory-audit-fixture.json'), json_encode(['assistantEmail' => $assistant->email, 'dashboardPath' => \App\Filament\Pages\DashboardImproved::getUrl(), 'email' => $user->email, 'deleteItems' => $deleteItems, 'item' => $item->id, 'source' => $source->id, 'destination' => $destination->id, 'pallet' => $pallet->id, 'pallets' => $pallets, 'line' => $line->id, 'adminEmail' => $admin->email, 'streamerEmail' => $reader->email, 'readerLocation' => $readerLocation->id, 'stockPath' => ltrim(parse_url(\App\Filament\Resources\InventoryStockResource::getUrl('index'), PHP_URL_PATH), '/')]));
echo "Isolated inventory browser fixtures ready.\n";
