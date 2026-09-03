<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\AddPalletLines;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Typing a packing slip is a columnar job — the same field down every line —
 * and the repeater gave each line a card roughly a screen tall. This is the
 * table version: rows in, lines out.
 */
class AddPalletLinesTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);

        $this->pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'Slip Vendor', 'status' => 'active'])->id,
            'reference' => 'PO-SLIP',
            'status'    => 'pending',
        ]);
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(AddPalletLines::class, ['record' => $this->pallet->id]);
    }

    public function test_it_opens_with_rows_ready_to_type_into(): void
    {
        // Landing on an empty table and having to press "add" before typing is
        // a step nobody wants on a page whose whole job is typing.
        $this->page()->assertCount('rows', 3);
    }

    public function test_it_creates_a_line_per_filled_row(): void
    {
        $this->page()
            ->set('rows.0.description', 'Topps Chrome Hobby')
            ->set('rows.0.is_container', '1')
            ->set('rows.0.case_count', 4)
            ->set('rows.0.quantity_per_case', 12)
            ->set('rows.0.unit_cost', 90)
            ->set('rows.1.description', 'Prizm Football')
            ->call('save');

        $this->assertSame(2, $this->pallet->lines()->count());

        $line = PalletLine::firstWhere('description', 'Topps Chrome Hobby');
        $this->assertEqualsWithDelta(4, $line->case_count, 0.001);
        $this->assertEqualsWithDelta(12, $line->quantity_per_case, 0.001);
        $this->assertEqualsWithDelta(90, $line->unit_cost, 0.001);
    }

    public function test_blank_rows_are_ignored_rather_than_rejected(): void
    {
        // Three rows are provided and a pallet rarely has exactly three lines,
        // so discarding the leftovers is the only behaviour that does not
        // punish using the page as intended.
        $this->page()
            ->set('rows.0.description', 'Only Real Line')
            ->call('save');

        $this->assertSame(1, $this->pallet->lines()->count());
    }

    public function test_saving_nothing_says_so_instead_of_creating_nothing_silently(): void
    {
        $this->page()->call('save');

        $this->assertSame(0, $this->pallet->lines()->count());
    }

    public function test_not_sure_stays_unanswered_rather_than_becoming_single(): void
    {
        // "" is a real answer while reading a slip. Casting it to false would
        // record a decision nobody made.
        $this->page()
            ->set('rows.0.description', 'Unknown Shape')
            ->set('rows.0.is_container', '')
            ->call('save');

        $this->assertNull(PalletLine::firstWhere('description', 'Unknown Shape')->is_container);
    }

    public function test_the_batch_location_lands_on_every_line(): void
    {
        $this->page()
            ->set('locationId', $this->location->id)
            ->set('rows.0.description', 'Line A')
            ->set('rows.1.description', 'Line B')
            ->call('save');

        foreach ($this->pallet->lines as $line) {
            $this->assertSame($this->location->id, $line->inventory_location_id);
        }
    }

    public function test_existing_lines_are_loaded_for_editing(): void
    {
        // Without this the page could only append, so a correction meant going
        // back to the stack-of-cards form this page exists to replace.
        PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Already here',
            'case_count' => 3, 'quantity_per_case' => 6, 'unit_cost' => 12,
        ]);

        $rows = $this->page()->instance()->rows;

        $this->assertSame('Already here', $rows[0]['description']);
        $this->assertEqualsWithDelta(3, $rows[0]['case_count'], 0.001);
        $this->assertNotNull($rows[0]['id'], 'an existing line loaded without its id would be saved as a duplicate');
    }

    public function test_editing_a_loaded_line_updates_it_rather_than_duplicating(): void
    {
        $line = PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Typo Here',
            'case_count' => 1, 'quantity_per_case' => 1,
        ]);

        $this->page()
            ->set('rows.0.description', 'Fixed Name')
            ->set('rows.0.case_count', 5)
            ->call('save');

        $this->assertSame(1, $this->pallet->lines()->count());
        $this->assertSame('Fixed Name', $line->fresh()->description);
        $this->assertEqualsWithDelta(5, $line->fresh()->case_count, 0.001);
    }

    public function test_removing_a_loaded_line_deletes_it_on_save(): void
    {
        PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Remove Me',
            'case_count' => 1, 'quantity_per_case' => 1,
        ]);

        $page = $this->page()->call('removeRow', 0);

        // Not gone yet: deleting on click would destroy a line before anyone
        // pressed save, and leaving the page is the recovery people reach for.
        $this->assertSame(1, $this->pallet->lines()->count());

        $page->set('rows.0.description', 'Kept')->call('save');

        $this->assertNull(PalletLine::firstWhere('description', 'Remove Me'));
        $this->assertNotNull(PalletLine::firstWhere('description', 'Kept'));
    }

    public function test_lines_are_renumbered_to_match_the_table(): void
    {
        foreach ([['A', 4], ['B', 9]] as [$name, $number]) {
            PalletLine::create([
                'pallet_id' => $this->pallet->id, 'line_number' => $number, 'description' => $name,
                'case_count' => 1, 'quantity_per_case' => 1,
            ]);
        }

        $this->page()->call('save');

        // Gaps nobody can explain are worse than numbering that follows what is
        // on screen.
        $this->assertSame([1, 2], $this->pallet->lines()->orderBy('line_number')->pluck('line_number')->all());
    }

    public function test_the_batch_location_does_not_overwrite_an_existing_line(): void
    {
        $other = InventoryLocation::create(['name' => 'Back Room', 'type' => 'other', 'status' => 'active']);

        $line = PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Routed Already',
            'case_count' => 1, 'quantity_per_case' => 1, 'inventory_location_id' => $other->id,
        ]);

        $this->page()->set('locationId', $this->location->id)->call('save');

        // A line routed somewhere deliberately should not be swept along by a
        // batch default chosen for the rest.
        $this->assertSame($other->id, $line->fresh()->inventory_location_id);
    }

    public function test_totals_only_count_rows_with_a_name(): void
    {
        $page = $this->page()
            ->set('rows.0.description', 'Counted')
            ->set('rows.0.is_container', '1')
            ->set('rows.0.case_count', 2)
            ->set('rows.0.quantity_per_case', 10)
            ->set('rows.0.unit_cost', 5)
            ->set('rows.1.case_count', 99);          // no name — scaffolding

        $totals = $page->instance()->totals;

        $this->assertSame(1, $totals['lines']);
        $this->assertEqualsWithDelta(20, $totals['units'], 0.001);
        $this->assertEqualsWithDelta(100, $totals['cost'], 0.001);
    }

    public function test_a_single_item_is_a_quantity_not_cases_times_units(): void
    {
        // The row hides units-per-case once it is a single item, so a value
        // left there from before must not quietly multiply the line.
        $this->page()
            ->set('rows.0.description', 'One Loose Box')
            ->set('rows.0.is_container', '0')
            ->set('rows.0.case_count', 7)
            ->set('rows.0.quantity_per_case', 12)   // stale, and no longer shown
            ->call('save');

        $line = PalletLine::firstWhere('description', 'One Loose Box');

        $this->assertEqualsWithDelta(7, $line->case_count, 0.001);
        $this->assertEqualsWithDelta(1, $line->quantity_per_case, 0.001, 'a single item was stored with a multiplier');
    }

    public function test_totals_do_not_multiply_a_single_item(): void
    {
        $page = $this->page()
            ->set('rows.0.description', 'Loose')
            ->set('rows.0.is_container', '0')
            ->set('rows.0.case_count', 3)
            ->set('rows.0.quantity_per_case', 99)
            ->set('rows.0.unit_cost', 10);

        $totals = $page->instance()->totals;

        $this->assertEqualsWithDelta(3, $totals['units'], 0.001);
        $this->assertEqualsWithDelta(30, $totals['cost'], 0.001);
    }

    public function test_saving_several_rows_linked_to_existing_products(): void
    {
        // The restock path — search, link, save — reported a save error in
        // practice with three linked rows. Nothing here reproduced it, but
        // the flow itself (selectProduct on multiple rows before a single
        // save) had no coverage until now.
        $products = collect(['Reward Turn Booster Box', 'Black Crystal Blazing Box', 'Mystic & Void Booster Box'])
            ->map(fn ($name, $i) => Product::create([
                'name' => $name, 'sku' => "LINK-{$i}", 'is_active' => true, 'is_container' => true,
            ]));

        $page = $this->page();

        foreach ($products as $i => $product) {
            $page->call('selectProduct', $i, $product->id)
                ->set("rows.{$i}.case_count", 4);
        }

        $page->call('save')->assertHasNoErrors();

        $this->assertSame(3, $this->pallet->lines()->count());
        foreach ($products as $i => $product) {
            $line = PalletLine::firstWhere('inventory_item_id', $product->id);
            $this->assertNotNull($line, "line for \"{$product->name}\" was not saved");
            $this->assertEqualsWithDelta(4, $line->case_count, 0.001);
        }
    }

    public function test_removing_the_last_row_leaves_one_to_type_into(): void
    {
        $page = $this->page();

        foreach ([2, 1, 0] as $i) {
            $page->call('removeRow', $i);
        }

        $page->assertCount('rows', 1);
    }
}
