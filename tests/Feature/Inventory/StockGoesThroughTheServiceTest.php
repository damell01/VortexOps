<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Stock cannot change without a record of it changing.
 *
 * Several screens wrote the stock row themselves. The mobile scanner wrote no
 * movement at all, so units added from a phone appeared in the totals and in no
 * history. The desktop scanner and the reconciliation page each wrote their own
 * movement beside the row — outside a transaction, with no before/after, and
 * with to_location_id set even when the quantity went down, which records a
 * shortfall as stock arriving. That last one is the same fault that had the log
 * reporting a reduction of twelve as "+12".
 *
 * The arithmetic, the locking, the direction and the levels either side are the
 * same whatever prompted the change, and that is exactly what was being
 * re-implemented slightly differently each time. These pin the rule rather than
 * the four call sites, because the next screen to write stock is the one that
 * matters.
 */
class StockGoesThroughTheServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;
    private InventoryLocation $location;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'TCH-1', 'barcode' => '999',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 20,
        ]);

        cache()->forget('inv_loc:active');
    }

    /**
     * No source file outside the services may write a stock quantity.
     *
     * A static check rather than a behavioural one, because the failure this
     * guards against is a screen that has not been written yet. Every
     * behavioural test in this file passes on a page that writes its own row
     * correctly; only this one notices that it did.
     */
    public function test_no_screen_writes_a_stock_quantity_for_itself(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // The services are where this belongs, and the duplicate merge is
            // a deliberate exception: it consolidates two records for one
            // thing, so nothing moved and its history is reassigned instead.
            if (str_contains($path, '/Services/') || str_contains($path, 'DuplicateProductDetector')) {
                continue;
            }

            // Matched per statement, not per file. A file that touches stock
            // somewhere and also increments a log line's quantity is not an
            // offender, and a check that cannot tell those apart gets muted
            // rather than fixed.
            $patterns = [
                // $stock->increment('quantity', …) and friends, by receiver name.
                '/\$\w*[sS]tock\w*->(increment|decrement)\(\s*.quantity./',
                // $stock->update(['quantity' => …])
                '/\$\w*[sS]tock\w*->update\(\s*\[\s*.quantity./',
                // InventoryStock::…->update(['quantity' => …])
                '/InventoryStock::[^;]{0,200}->update\(\s*\[\s*.quantity./s',
            ];

            $source = file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source, $m)) {
                    $offenders[] = basename($path) . ' — ' . trim($m[0]);
                }
            }
        }

        $this->assertSame(
            [],
            array_unique($offenders),
            "These write stock directly instead of going through InventoryService:\n"
            . implode("\n", array_unique($offenders)),
        );
    }

    // ── Each screen that books stock ──────────────────────────────────────

    public function test_the_desktop_scanner_records_a_reduction_as_a_reduction(): void
    {
        $page = new \App\Filament\Pages\InventoryScanner();
        $page->scanInput = 'TCH-1';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '-5';
        $page->applyAdjust();

        $movement = InventoryMovement::latest('id')->first();

        $this->assertEqualsWithDelta(-5.0, $movement->signedChange(), 0.01);
        $this->assertSame($this->location->id, $movement->from_location_id);
        $this->assertNull($movement->to_location_id, 'A reduction is not stock arriving.');
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $movement->quantity_after, 0.01);
    }

    public function test_the_desktop_scanner_records_an_increase_as_an_increase(): void
    {
        $page = new \App\Filament\Pages\InventoryScanner();
        $page->scanInput = 'TCH-1';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '5';
        $page->applyAdjust();

        $movement = InventoryMovement::latest('id')->first();

        $this->assertEqualsWithDelta(5.0, $movement->signedChange(), 0.01);
        $this->assertSame($this->location->id, $movement->to_location_id);
        $this->assertEqualsWithDelta(25.0, (float) $movement->quantity_after, 0.01);
    }

    public function test_quick_add_leaves_a_movement_behind(): void
    {
        $page = new \App\Filament\Pages\InventoryScanner();
        $page->switchMode('quickadd');
        $page->scanInput    = '999';
        $page->qaLocationId = $this->location->id;
        $page->qaQty        = '3';
        $page->submitScan();

        $movement = InventoryMovement::latest('id')->first();

        $this->assertNotNull($movement);
        $this->assertEqualsWithDelta(3.0, $movement->signedChange(), 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.01);
        $this->assertEqualsWithDelta(23.0, (float) $movement->quantity_after, 0.01);

        $this->assertEqualsWithDelta(
            23.0,
            (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'),
            0.01,
        );
    }

    public function test_a_stocktake_keeps_its_own_name_and_the_right_direction(): void
    {
        // A count that comes in low is a shortfall, not a delivery — and it is
        // still worth being able to tell a stocktake from an adjustment
        // afterwards, so the movement type survives the move to the service.
        app(\App\Services\InventoryService::class)->adjustStock(
            $this->item,
            $this->location,
            17,
            'annual count',
            'reconciliation',
        );

        $movement = InventoryMovement::latest('id')->first();

        $this->assertSame('reconciliation', $movement->movement_type);
        $this->assertEqualsWithDelta(-3.0, $movement->signedChange(), 0.01);
        $this->assertSame($this->location->id, $movement->from_location_id);
        $this->assertEqualsWithDelta(17.0, (float) $movement->quantity_after, 0.01);
    }
}
