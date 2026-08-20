<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ViewInventoryItem;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every tab has to survive a second request.
 *
 * mount() runs once. Every Livewire round trip after it — which is every tab
 * switch — re-hydrates the record straight from the database with no relations
 * loaded, and lazy loading is disabled outside production. So relations eager
 * loaded in mount() are present on the first render and gone on the next, and
 * the first thing to touch one throws.
 *
 * That is what these cover, and why each one switches tabs rather than
 * rendering a tab directly: a test that only mounts the page passes against
 * exactly the bug that made every tab switch return a 500 and a megabyte of
 * error page — which read, from the outside, as the tabs being slow.
 */
class InventoryItemTabsTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-1', 'barcode' => '111',
            'unit_cost' => 100, 'average_cost' => 100, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 10,
        ]);

        // Real received history, so the tabs have something to render rather
        // than passing on empty collections.
        $pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'name'      => 'August Pallet',
            'status'    => 'receiving',
        ]);

        $line = $pallet->lines()->create([
            'line_number'           => 1,
            'description'           => 'Chrome Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $location->id,
            'case_count'            => 3,
            'quantity_per_case'     => 2,
            'unit_cost'             => 100,
        ]);

        $receiving = app(ReceivingService::class);
        $receiving->generateExpectedCases($line->refresh());

        foreach ($line->cases as $case) {
            $receiving->receiveCase($case);
        }
    }

    /** @return array<int, array<int, string>> */
    public static function tabs(): array
    {
        return [
            ['overview'],
            ['stock'],
            ['lots'],
            ['receiving'],
            ['movements'],
            ['cost'],
            ['analysis'],
            ['aliases'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function test_a_tab_renders_after_switching_to_it(string $tab): void
    {
        Livewire::test(ViewInventoryItem::class, ['record' => $this->item])
            ->call('setTab', $tab)
            ->assertOk()
            ->assertHasNoErrors();
    }

    public function test_switching_between_every_tab_in_turn_survives(): void
    {
        // The real sequence. A page that survives one switch can still fail on
        // the third, because each round trip re-hydrates from scratch.
        $page = Livewire::test(ViewInventoryItem::class, ['record' => $this->item]);

        foreach (array_merge(array_column(self::tabs(), 0), ['overview']) as $tab) {
            $page->call('setTab', $tab)->assertOk();
        }
    }

    public function test_repeated_receipts_are_one_row_carrying_the_total(): void
    {
        // Three cases of two received together is "+6" once, not "+2" three
        // times — and the row says how many scans it stands for so a merged
        // row cannot be mistaken for lost ones.
        $movements = Livewire::test(ViewInventoryItem::class, ['record' => $this->item])
            ->call('setTab', 'movements')
            ->instance()
            ->movements;

        $this->assertCount(1, $movements);
        $this->assertSame('+6', $movements[0]['label']);
        $this->assertSame(3, $movements[0]['grouped']);
    }

    public function test_a_receipt_reports_what_it_cost(): void
    {
        // Receiving writes unit_cost onto the movement now, so cost history has
        // a figure rather than falling through to "$0.00" against an item
        // averaging a hundred.
        $costs = Livewire::test(ViewInventoryItem::class, ['record' => $this->item])
            ->call('setTab', 'cost')
            ->instance()
            ->costHistory;

        $this->assertNotEmpty($costs);
        $this->assertEqualsWithDelta(100.0, $costs[0]['unit_cost'], 0.01);
    }
}
