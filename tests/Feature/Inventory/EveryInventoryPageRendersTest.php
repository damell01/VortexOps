<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Open every inventory screen and see that it opens.
 *
 * Two of these shipped broken and the suite was green throughout, because
 * nothing rendered them: a Blade view is compiled the first time it is asked
 * for, so a syntax error waits in the file until a person opens that page.
 * Unit tests over services never ask.
 *
 * These render each screen against data that looks like a working warehouse —
 * stock in two places, a pallet part-received, movements behind it — because
 * the empty-database version of a page exercises none of the formatting,
 * counting and relation-loading that actually breaks. Where a page is only
 * reachable with a record, it gets one.
 */
class EveryInventoryPageRendersTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    private InventoryLocation $main;

    private InventoryLocation $back;

    private Pallet $pallet;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->vendor = Vendor::create(['name' => 'Sweep Vendor', 'status' => 'active']);

        $this->main = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->back = InventoryLocation::create(['name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Hobby Box', 'sku' => 'SWEEP-1', 'barcode' => '012345678905',
            'unit_cost' => 80, 'average_cost' => 82.5, 'is_active' => true,
            'preferred_vendor_id' => $this->vendor->id,
        ]);

        // Stock in two places, so anything that sums or groups by location has
        // more than one row to get wrong.
        InventoryStock::create([
            'inventory_item_id' => $this->item->id,
            'inventory_location_id' => $this->main->id, 'quantity' => 24,
        ]);
        InventoryStock::create([
            'inventory_item_id' => $this->item->id,
            'inventory_location_id' => $this->back->id, 'quantity' => 6,
        ]);

        InventoryMovement::create([
            'inventory_item_id' => $this->item->id,
            'to_location_id'    => $this->main->id,
            'quantity'          => 24,
            'quantity_before'   => 0,
            'quantity_after'    => 24,
            'movement_type'     => 'receipt',
            'reason'            => 'Opening stock',
        ]);

        $this->pallet = Pallet::create([
            'vendor_id' => $this->vendor->id,
            'reference' => 'PO-SWEEP',
            'status'    => 'pending',
            'shipping_cost' => 40,
            'payment_fees'  => 12,
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $this->pallet->id,
            'line_number'           => 1,
            'description'           => 'Chrome Hobby Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => 4,
            'units_per_case'        => 12,
            'unit_cost'             => 80,
        ]);

        // Part-received, which is the state a receiving screen is usually in
        // when someone actually looks at it.
        InventoryCase::create([
            'pallet_line_id' => $line->id,
            'barcode'        => 'CASE-1',
            'status'         => 'received',
            'received_at'    => now(),
        ]);
        InventoryCase::create([
            'pallet_line_id' => $line->id,
            'barcode'        => 'CASE-2',
            'status'         => 'expected',
        ]);
    }

    /**
     * Pages that stand on their own, with no record in the URL.
     *
     * @return array<string, array{class-string}>
     */
    public static function standalonePages(): array
    {
        $pages = [
            \App\Filament\Pages\InventoryScanner::class,
            \App\Filament\Pages\InventorySearch::class,
            \App\Filament\Pages\InventoryReport::class,
            \App\Filament\Pages\InventoryAge::class,
            \App\Filament\Pages\InventoryAnalytics::class,
            \App\Filament\Pages\InventoryGuide::class,
            \App\Filament\Pages\InventoryValueDashboard::class,
            \App\Filament\Pages\InventoryVelocityAnalytics::class,
            \App\Filament\Pages\InventoryReconciliation::class,
            \App\Filament\Pages\DuplicateProductDetector::class,
            \App\Filament\Pages\BarcodePrinter::class,
            \App\Filament\Pages\QuickAddStock::class,
            \App\Filament\Pages\QuickAddContainerScan::class,
            \App\Filament\Pages\StockTransfer::class,
            \App\Filament\Pages\MobileScannerApp::class,
            \App\Filament\Pages\MobileScannerHub::class,
            \App\Filament\Pages\MobileScannerPro::class,
            \App\Filament\Pages\PalletStatusDashboard::class,
            \App\Filament\Pages\PalletReceivingHistory::class,
            \App\Filament\Pages\ReceivingAnalytics::class,
            \App\Filament\Pages\ReceivingSessionsHistory::class,

            \App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems::class,
            \App\Filament\Resources\InventoryItemResource\Pages\CreateInventoryItem::class,
            \App\Filament\Resources\InventoryItemResource\Pages\QuickAddInventoryItem::class,
            \App\Filament\Resources\InventoryLocationResource\Pages\ListInventoryLocations::class,
            \App\Filament\Resources\InventoryLocationResource\Pages\CreateInventoryLocation::class,
            \App\Filament\Resources\InventoryMovementResource\Pages\ListInventoryMovements::class,
            \App\Filament\Resources\InventoryStockResource\Pages\ListInventoryStock::class,
            \App\Filament\Resources\ProductIdentityResource\Pages\ListProductIdentities::class,
            \App\Filament\Resources\PalletResource\Pages\ListPallets::class,
            \App\Filament\Resources\PalletResource\Pages\CreatePallet::class,
            \App\Filament\Resources\VendorResource\Pages\ListVendors::class,
            \App\Filament\Resources\VendorResource\Pages\CreateVendor::class,
        ];

        $out = [];
        foreach ($pages as $page) {
            $out[class_basename($page)] = [$page];
        }

        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('standalonePages')]
    public function test_the_page_opens(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        Livewire::test($page)->assertOk();
    }

    // ── Pages that need a record ──────────────────────────────────────────────

    /**
     * Mount a record-bound page, passing whatever its $record property accepts.
     *
     * Filament's own pages resolve a key through InteractsWithRecord, but the
     * hand-written ones here declare `public Pallet $record` and are handed the
     * value directly, so a key fails on assignment. Reading the declared type
     * keeps this test from encoding which page is which.
     */
    private function open(string $page, \Illuminate\Database\Eloquent\Model $record): \Livewire\Features\SupportTesting\Testable
    {
        $type = (new \ReflectionClass($page))->hasProperty('record')
            ? (new \ReflectionProperty($page, 'record'))->getType()
            : null;

        // Union types are the common case on Filament's own pages, which accept
        // an int|string key as well as the model.
        $names = match (true) {
            $type instanceof \ReflectionNamedType => [$type->getName()],
            $type instanceof \ReflectionUnionType => array_map(
                fn ($t) => $t instanceof \ReflectionNamedType ? $t->getName() : '',
                $type->getTypes(),
            ),
            default => [],
        };

        $takesModel = (bool) array_filter(
            $names,
            fn ($n) => $n !== '' && class_exists($n) && is_a($record, $n),
        );

        // A key is accepted wherever one of the union members is a scalar, and
        // that is the path production takes, so prefer it.
        $takesKey = (bool) array_filter($names, fn ($n) => in_array($n, ['int', 'string'], true));

        return Livewire::test($page, [
            'record' => ($takesKey || ! $takesModel) ? $record->getKey() : $record,
        ]);
    }

    public function test_the_item_page_opens(): void
    {
        $this->open(\App\Filament\Resources\InventoryItemResource\Pages\ViewInventoryItem::class, $this->item)
            ->assertOk();
    }

    public function test_the_item_edit_page_opens(): void
    {
        $this->open(\App\Filament\Resources\InventoryItemResource\Pages\EditInventoryItem::class, $this->item)
            ->assertOk();
    }

    public function test_the_stock_page_opens(): void
    {
        $this->open(\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::class, $this->item)
            ->assertOk();
    }

    public function test_the_location_pages_open(): void
    {
        $this->open(\App\Filament\Resources\InventoryLocationResource\Pages\ViewInventoryLocation::class, $this->main)
            ->assertOk();

        $this->open(\App\Filament\Resources\InventoryLocationResource\Pages\EditInventoryLocation::class, $this->main)
            ->assertOk();
    }

    public function test_the_movement_page_opens(): void
    {
        $this->open(\App\Filament\Resources\InventoryMovementResource\Pages\ViewInventoryMovement::class, InventoryMovement::first())
            ->assertOk();
    }

    public function test_the_stock_row_edit_page_opens(): void
    {
        $this->open(\App\Filament\Resources\InventoryStockResource\Pages\EditInventoryStock::class, InventoryStock::first())
            ->assertOk();
    }

    public function test_the_vendor_pages_open(): void
    {
        $this->open(\App\Filament\Resources\VendorResource\Pages\ViewVendor::class, $this->vendor)
            ->assertOk();

        $this->open(\App\Filament\Resources\VendorResource\Pages\EditVendor::class, $this->vendor)
            ->assertOk();
    }

    /**
     * The pallet screens, which is where the compile error was hiding.
     *
     * @return array<string, array{class-string}>
     */
    public static function palletPages(): array
    {
        return [
            'ViewPallet'     => [\App\Filament\Resources\PalletResource\Pages\ViewPallet::class],
            'EditPallet'     => [\App\Filament\Resources\PalletResource\Pages\EditPallet::class],
            'AddPalletLines' => [\App\Filament\Resources\PalletResource\Pages\AddPalletLines::class],
            'ReceivePallet'  => [\App\Filament\Resources\PalletResource\Pages\ReceivePallet::class],
            'PalletItems'    => [\App\Filament\Resources\PalletResource\Pages\PalletItems::class],
            'ImportManifest' => [\App\Filament\Resources\PalletResource\Pages\ImportManifest::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('palletPages')]
    public function test_the_pallet_page_opens(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        $this->open($page, $this->pallet)->assertOk();
    }

    public function test_the_retired_staging_route_lands_on_the_pallet(): void
    {
        // Not a 200, deliberately. Staging was folded into the pallet's own
        // page and this route survives only so an old bookmark arrives
        // somewhere useful rather than on a 404 — so the redirect is the
        // behaviour, and a plain "does it render" check would read it as a
        // failure.
        $this->open(\App\Filament\Resources\PalletResource\Pages\StagePallet::class, $this->pallet)
            ->assertRedirect(\App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->pallet]));
    }
}
